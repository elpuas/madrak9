# Shop Commands

## Purpose

Shop Commands provides useful store operations for WooCommerce Shop Managers
through the WordPress Abilities API.

The goal is to reduce the number of admin screens a Shop Manager needs to
navigate for common day-to-day tasks.

Shop Commands is standalone. It defines store capabilities and owns the
Command Palette integration used to discover, execute, and display supported
Abilities.

## Architecture

Keep these responsibilities separate:

Shop Commands
→ WordPress Abilities API
→ WordPress Command Palette
→ reusable Shop Commands result modal

### Shop Commands owns

- WooCommerce-specific business logic
- Ability registration
- Input and output schemas
- Permission checks
- WooCommerce data retrieval
- Command Palette registration for supported Abilities
- client-side Ability execution
- generic schema-driven result rendering
- WordPress-native modal feedback

## Current Scope

The initial release is read-only and focused on orders.

Start with these Abilities:

1. Orders needing attention
2. Today's orders
3. Recent orders
4. Find order

Do not add write operations, order mutations, settings screens, custom admin
pages, or unrelated store features without explicit approval.

Build one Ability at a time.

## Primary User

The primary user is a WooCommerce Shop Manager.

Use WordPress/WooCommerce capabilities for authorization.

Do not check role names directly.

Do not assume Administrator-only access.

## WordPress Abilities

Register operations using the WordPress Abilities API.

Each Ability should:

- have a clear, human-readable label
- have a useful description
- define an `output_schema`
- define an `input_schema` only when input is required
- use an appropriate `permission_callback`
- use public WordPress and WooCommerce APIs
- be marked appropriately as read-only/idempotent when supported
- return structured data suitable for multiple consumers

Do not design an Ability specifically around the Command Palette UI.

An Ability should remain useful independently of the Shop Commands UI.

## Ability Naming

Use the plugin namespace consistently:

`shop-commands/<ability-name>`

Examples:

- `shop-commands/orders-needing-attention`
- `shop-commands/todays-orders`
- `shop-commands/recent-orders`
- `shop-commands/find-order`

Names should describe the capability, not the UI that invokes it.

## Output Design

Prefer small, predictable structured responses.

Example:

```json
{
	"processing": 4,
	"on_hold": 2,
	"failed": 1
}
```

Use schema titles and descriptions when they improve generic presentation.

Do not return HTML for presentation.

Do not couple output to the Shop Commands modal implementation.

When useful, an Ability may return a URL that lets a consumer navigate to the
corresponding WooCommerce admin screen.

Keep data and navigation information explicit in the schema.

## WooCommerce

There is currently no WooCommerce-specific Codex Skill in this workspace.

Before relying on a WooCommerce API:

1. Inspect the WooCommerce version installed in this environment.
2. Verify the relevant public API against the installed source when necessary.
3. Prefer WooCommerce CRUD/query APIs.

Prefer APIs such as:

- `wc_get_orders()`
- `wc_get_order()`
- WooCommerce order objects
- documented WooCommerce CRUD APIs

Avoid direct database queries.

Maintain HPOS compatibility.

Do not rely on legacy `wp_posts` / `wp_postmeta` order storage.

Do not reproduce calculations already provided by WooCommerce APIs.

## Skills

Use the workspace Skills instead of reproducing their instructions here.

For Ability work, consult the relevant Skills before implementation:

- `.codex/skills/wp-abilities-api`
- `.codex/skills/wp-abilities-verify`
- `.codex/skills/wp-abilities-audit`
- `.codex/skills/wp-plugin-development`

Use additional workspace Skills when the task requires them.

Skills provide implementation guidance.
This file provides project-specific decisions and boundaries.

## Working Method

Before modifying code:

1. Read this file.
2. Read the relevant workspace Skill(s).
3. Inspect the existing plugin.
4. Inspect the installed WordPress/WooCommerce APIs when needed.
5. State a short implementation plan for non-trivial changes.

Keep changes scoped to the requested Ability or task.

Do not refactor unrelated code while implementing a feature.

Do not introduce abstractions until more than one concrete use case requires
them.

Prefer simple WordPress/PHP code over framework-like architecture.

## Verification

Every Ability must be verified independently from the Command Palette first.

At minimum verify:

- registration succeeds
- the Ability is discoverable
- permission checks behave correctly
- execution succeeds
- returned data matches the declared schema
- unauthorized execution is rejected
- WooCommerce queries work with HPOS

Use the workspace Ability verification/audit Skills where applicable.

After the Ability works independently, it may be tested through the Shop
Commands Command Palette integration.

Do not treat Command Palette behavior as proof that the underlying Ability is
correct.

## Testing Roles

Where authorization is relevant, verify at least:

- Administrator
- Shop Manager
- a user without `manage_woocommerce`

The exact capability may vary by Ability. Use the narrowest appropriate
capability rather than defaulting automatically to `manage_options`.

## Safety for Store Data

The initial scope is read-only.

Do not:

- modify order status
- add order notes
- create or delete orders
- modify products
- change inventory
- trigger emails
- trigger payments or refunds

unless the task explicitly introduces a write Ability and its confirmation
behavior has been agreed first.

## Repository Discipline

Keep commits focused and easy to review.

Do not commit:

- credentials
- API keys
- customer data
- database dumps
- generated vendor/build artifacts unless the repository explicitly requires them

Do not deploy, push, publish, or create releases unless explicitly requested.
