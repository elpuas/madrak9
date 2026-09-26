# AI Compose Block — Design

## Summary

A new Gutenberg block, `ollie/ai-compose`, that combines the inline-prompt UX of the existing Design Prompt block (`ollie/pattern-prompt`) with the WP-AI-backed generation pipeline already used by AI Rewrite. Inserts a slim, inline prompt input into the page; the user types what they want generated, optionally attaches context (pages or files); on submit, the WP AI plugin's configured provider produces semantic HTML; the HTML is parsed into core blocks and rendered as the block's inner blocks, with Apply / Try Again controls. Apply replaces the wrapper with the generated blocks.

The block ships alongside Design Prompt at the plugin root (`src/ai-compose/`) and is always available — no Extensions-panel toggle.

## Goals

- Provide an in-canvas content generator that matches the inline-prompt feel of Design Prompt.
- Reuse the AI Rewrite ability pipeline (WP AI plugin + per-request fenced prompt + provider abstraction) so we don't reimplement the provider plumbing.
- Establish a shared-AI-utilities home (`inc/shared/ai/`) that future AI-driven blocks and editor extensions can build on without forcing a refactor.
- Keep the surface area small: one ability, one block, one shared module.

## Non-goals (MVP)

- Multiple variations per prompt (single output for now).
- Streaming responses (request/response).
- Role-aware composition (e.g., "this is a hero section title") — the model gets the prompt and any context, nothing more.
- Regenerate-with-changes flow (only Try Again resets the prompt).
- Extensions-panel toggle / dashboard entry.

## User flow

1. User inserts an **AI Compose** block (or uses slash inserter `/AI Compose`).
2. Block renders the **prompt phase**: a single-line growing textarea with placeholder text, a paperclip context-picker chip row below it, and a paper-plane submit button.
3. User types a prompt (e.g. "Write a three-paragraph intro about why Pilates is a great low-impact workout"), optionally attaches one or more context pages, presses Enter or clicks submit.
4. Block transitions to **loading phase**: input area shows the dot-grid animation and the message "Composing your content…".
5. On response, the HTML is sanitized, parsed into core blocks via `wp.blocks.rawHandler`, and rendered as inner blocks of the AI Compose wrapper.
6. Block transitions to **results phase**: `<InnerBlocks />` shows the generated content. BlockControls toolbar exposes **Apply** and **Try Again**.
7. **Apply** calls `replaceBlock(clientId, innerBlocks)` — the wrapper disappears, generated blocks become the page content.
8. **Try Again** clears inner blocks and returns to prompt phase with the previous prompt intact (mirrors Design Prompt).

If the WP AI plugin is missing or no provider is connected, the prompt phase shows a small inline notice ("AI Compose needs a connected AI provider. Configure your AI provider.") linking to `wp-admin/options-connectors.php`, and the submit button is disabled. Server-side failures during submit surface a transient error below the input.

## Architecture

### Block (frontend)

Lives at `src/ai-compose/`:

- `block.json` — registers `ollie/ai-compose`, category `widgets`, keywords (`ai`, `compose`, `generate`, `prompt`, `ollie`). Editor script `index.js`, editor style `index.css`.
- `index.js` — block registration; tiny.
- `edit.jsx` — phase state machine, prompt textarea, submit, ContextPicker, InnerBlocks renderer, BlockControls with Apply/Try Again.
- `save.jsx` — returns `<InnerBlocks.Content />` so generated content is persisted; the wrapper itself contributes no markup beyond its block delimiters (or, more cleanly, the block uses `save: () => null` and is removed at Apply time before publish — see decision below).
- `editor.scss` — phase-class-based styling (`is-phase-prompt`, `is-phase-loading`, `is-phase-results`), shared visual identity with Design Prompt (white card, soft shadow, dark submit button).

**Save behaviour**: the wrapper block exists only in the editor as a generation surface. After Apply, the wrapper is replaced and gone. If the user leaves the wrapper unaplied and publishes, the generated InnerBlocks should still render (so the user doesn't lose work). Use `<InnerBlocks.Content />` in save so the inner blocks persist; the wrapper renders as a passthrough `<div>` with no styling impact.

### Backend ability

Lives at `inc/extensions/loader/ai-compose/` (mirrors the AI Rewrite loader layout). Registered in `inc/extensions/class-olpo-extensions-handler.php` next to the AI Rewrite ability.

- `ai-compose.php` — registers `ollie/content-compose` via `wp_register_ability` on `wp_abilities_api_init`, guarded by `class_exists( 'WordPress\\AI\\Abstracts\\Abstract_Ability' )` (same guard as ai-rewrite).
- `class-content-compose.php` — extends `Abstract_Ability`. Input schema: `prompt` (string, required, sanitize_textarea_field), `context` (string, optional post id). Output schema: `content` (string, HTML). Executes by building a fenced user prompt (random per-request token, same prompt-injection defence as the rewrite ability) and calling `wp_ai_client_prompt( $user_prompt )->using_system_instruction( ... )->using_model_preference( ...get_preferred_models_for_text_generation() )->generate_text()->get_text()`.
- `system-instruction.php` — defines the model's role and output contract: returns semantic HTML using `h2`, `h3`, `h4`, `p`, `ul`, `ol`, `li`, `blockquote`, plus inline `strong`, `em`, `a`, `code`. No markdown, no code fences, no Gutenberg block comments. Matches the user's intent as written. Avoids preambles, sign-offs, and labels.

### Shared AI module

Lives at `inc/shared/ai/`. New top-level directory under `inc/` for code that crosses the plugin-root and extensions build contexts.

- `context-picker.jsx` — the `<ContextPicker>` React component (currently inline inside `ai-rewrite/index.js`).
- `use-context-pages.js` — the `useContextPages` hook (selection + persisted-context cache).
- `get-context-content.js` — `getContextContent( contextPages )` (fetches and stitches together attached context bodies).
- `sanitize-html.js` — renamed and parameterized version of the current `sanitizeInlineHtml`. Accepts an `allowlist` object (tag → allowed attrs map) and a `dropList` set. AI Rewrite passes the existing inline-only allowlist. AI Compose passes a richer allowlist that adds block-level tags (`h2`, `h3`, `h4`, `p`, `ul`, `ol`, `li`, `blockquote`) on top of the inline set.

Both consumers import via relative paths. Each webpack context compiles its own copy at build time — acceptable; the source is one file. No alias setup required.

### Data flow on submit

```
edit.jsx (handleSubmit)
  → useContextPages → getContextContent( attached pages )
  → apiFetch POST /wp-abilities/v1/abilities/ollie/content-compose/run
      with body { input: { prompt, context } }
  → server: Ollie_Content_Compose::execute()
      → build fenced user prompt
      → wp_ai_client_prompt( user_prompt )
          ->using_system_instruction( system-instruction.php )
          ->using_model_preference( ...get_preferred_models_for_text_generation() )
          ->generate_text()->get_text()
      → return { content: <html> }
  → edit.jsx: sanitizeHtml( response.content, COMPOSE_ALLOWLIST )
  → wp.blocks.rawHandler( { HTML: sanitized } )
  → dispatch core/block-editor replaceInnerBlocks( clientId, blocks )
  → setPhase('results')
```

### Phase-based render

Same scaffolding as Design Prompt:

| Phase | Renders |
|---|---|
| `prompt` | `<textarea>` + submit button + ContextPicker + optional provider-not-connected notice |
| `loading` | dot-grid animation + "Composing your content…" |
| `results` | `<BlockControls>` (Apply / Try Again) + `<InnerBlocks />` |

CSS phase classes match the existing pattern-prompt approach (`is-phase-prompt | -loading | -results`).

## Reuse plan

| Concern | Source today | After this work |
|---|---|---|
| Context-picker UI | inline in `ai-rewrite/index.js` | `inc/shared/ai/context-picker.jsx` |
| `useContextPages` hook | inline in `ai-rewrite/index.js` | `inc/shared/ai/use-context-pages.js` |
| `getContextContent` | inline in `ai-rewrite/index.js` | `inc/shared/ai/get-context-content.js` |
| HTML sanitizer | `sanitizeInlineHtml` in `ai-rewrite/index.js` | `inc/shared/ai/sanitize-html.js` (parameterized) |
| AI provider plumbing (server) | `wp_ai_client_prompt` (WP AI plugin) | unchanged — both abilities use it directly |
| Prompt-injection fence | inline in `class-content-rewrite.php` | duplicated in `class-content-compose.php` (small enough to inline, not extracted in this pass) |

AI Rewrite is refactored to import from the new shared module. No behaviour change for AI Rewrite; same allowlist passed in.

## Error handling

- **No AI plugin / no provider at page-load** → block shows an inline notice in the prompt phase and disables submit.
- **No AI plugin at submit time (race)** → server returns a `WP_Error`; client surfaces "AI Compose requires the WordPress AI plugin." with the same configure link.
- **Provider error during generation** → server catches `Throwable`, returns `WP_Error( 'ai_compose_failed', $message )`; client surfaces the message under the textarea, leaves the prompt intact, returns to `prompt` phase.
- **Empty / unparseable response** → after sanitizing and `rawHandler`-ing, if zero blocks come back, surface "No content was generated. Please try a different prompt." and return to `prompt`.
- **Permission** → ability's `permission_callback` requires `edit_posts` (same as AI Rewrite).

## Security considerations

- Same per-request fence-token prompt-injection guard as AI Rewrite: random 16-char hex token wraps the user prompt and context; system instruction tells the model only fenced tags are framing.
- HTML sanitization runs in an inert `<template>` element with attribute-name and namespaced-attribute stripping. Block-level allowlist additions don't introduce new attribute risks because the parameterized sanitizer treats them the same way it treats inline tags.
- Capability check via `permission_callback`: `current_user_can( 'edit_posts' )`.
- No rate limiting in this pass (acknowledged limitation; tracked separately from this spec).

## Open decisions captured

- **Save format**: `<InnerBlocks.Content />` in `save.jsx` so unappied-but-published wrappers don't drop content. Apply remains the canonical commit path.
- **Submit on Enter**: matches Design Prompt. Shift+Enter inserts newline.
- **Try Again preserves the previous prompt text**: matches Design Prompt's Regenerate behaviour.

## Implementation order

1. Extract shared AI module from AI Rewrite (`context-picker`, `use-context-pages`, `get-context-content`, parameterized `sanitize-html`). Update AI Rewrite to import from there; verify no behaviour change.
2. Create the AI Compose backend ability + system instruction, registered behind the same WP-AI-plugin guard.
3. Scaffold `src/ai-compose/` block (block.json, index.js, edit.jsx, save.jsx, editor.scss). Wire up the three phases, ContextPicker, submit pipeline, Apply / Try Again.
4. Verify in the editor: insert block, run the prompt with and without context, confirm Apply removes the wrapper and persists blocks, confirm Try Again resets, confirm provider-not-connected notice appears.

## Risks

- **`rawHandler` output quality** depends on how cleanly the AI sticks to the allowed tag set. The sanitizer is the safety net; whatever it strips, rawHandler still handles gracefully (drops unknown elements, keeps text).
- **Two webpack contexts duplicating the shared source** means changes to shared utilities need both bundles rebuilt. The watch processes already cover this.
- **Block-level sanitizer allowlist drift** between AI Rewrite (inline-only) and AI Compose (inline + block). Mitigated by parameterizing the sanitizer with the allowlist passed in by the caller — single source of truth, two configurations.
