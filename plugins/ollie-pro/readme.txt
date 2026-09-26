=== Ollie Pro ===
Contributors:      patrickposner, mmcalister
Tags:              ollie, onboarding
Requires at least: 6.3
Tested up to:      7.0
Stable tag:        2.8.3
Requires PHP:      7.4
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Adds the Ollie Pro pattern library and Ollie Pro Dashboard to the Ollie block theme.

== Description ==

Ollie Pro is a premium plugin that adds even more WordPress patterns to your site via a cloud pattern library with an intuitive and powerful interface.

== Installation ==

You can install the Ollie Pro plugin by going to Plugins → Add New Plugin → Upload Plugin and uploading the ollie-pro.zip file.

== Frequently Asked Questions ==

= How do I use this plugin? =

Once installed and activated, go to Appearance → Ollie to active your Ollie Pro account and unlock pro features. Visit the Ollie Docs for detailed video tutorials and articles about Ollie and Ollie Pro. https://olliewp.com/docs

= Can I use Ollie Pro with a custom block theme? =

Yes. Ollie Pro automatically supports custom Ollie-based parent themes whose directory slug starts with `ollie-`, including child themes that use one of those themes as their parent. The custom parent theme must provide the Ollie design system, including the theme.json settings, style slugs, stylesheets, and other assets used by Ollie Pro features.

For a differently named parent theme, use the `ollie_pro_is_theme_compatible` filter from the theme's functions.php:

`add_filter( 'ollie_pro_is_theme_compatible', function( $is_compatible, $theme ) { return $is_compatible || 'my-parent-theme' === $theme->get_template(); }, 10, 2 );`

Returning `false` from the filter disables Ollie Pro for the active theme.

== Changelog ==

= 2.8.3 =
* Fix padding drag handles hugging the content width instead of the block edges on full-width groups with content width enabled
* Fix source map 404 errors in the browser console on production sites
* Close the Ollie Pro pattern modal with the Escape key

= 2.8.2 =
* Fix carousel autoplay stopping permanently on mobile when tapping or scrolling past it — only deliberate swipes pause it now
* Add Free scroll setting to the Carousel block to glide with momentum instead of snapping to each slide
* Let fast flicks skip past multiple slides for smoother carousel scrolling

= 2.8.1 =
* Move hover color controls into WordPress 7.1's split Typography, Background, and Border panels, with transition settings alongside each hover color
* Make group links keyboard and screen reader accessible, with focus styles and a screen reader label setting
* Move the stack layout's Stretch full height setting into the block toolbar
* Add a custom SVG option to WordPress 7.1's core Icon block, with the markup sanitized on save and render
* Choose any icon from the icon library to use on the button block
* Add descriptive screen reader labels to video modal triggers, with a custom label setting and block name fallback

= 2.8.0 =
* Introduce visual padding drag handles for Group, Cover, and Carousel blocks — adjust padding right on the canvas with spacing preset snapping, linked sides, and Shift to drag all sides at once
* Hand off tablet and mobile styles to WordPress 7.1's new built-in responsive editing
* Add the Ollie icon library to WordPress 7.1's core Icon block — all 1,055 Phosphor, payment, and badge icons appear in their own library tabs with no setup

= 2.7.1 =
* Add Dynamic Patterns option to the pattern library filter for finding patterns that offer a dynamic version
* Add Scroll to navigate setting to the Carousel block for trackpad and mouse wheel navigation
* Allow half-step Slides per view values in the Carousel block, like 1.5, to show a partial next slide

= 2.7.0 =
* Add new Carousel block suite with arrow, dot, and bar navigation, autoplay, fade, loop, and responsive per-device slide settings
* Add dynamic carousel slides that generate slides from your posts with post type, taxonomy, author, keyword, and offset controls
* Add Motion Gradient to the Cover block with generated gradient backgrounds, curated color designs, light and dark modes, and optional animation
* Add Texture to the Cover block with dots, grid, grain, and halftone options

= 2.6.4 =
* Fix pattern compatibility filtering hiding gated patterns inside iframed editors
* Improve empty states when searches or categories have no matching patterns

= 2.6.3 =
* Filter out cloud patterns that require a newer version of Ollie Pro
* Fix multi-word pattern searches failing due to invalid search syntax

= 2.6.2 =
* Fix empty buttons inside linked Groups inheriting the group URL so card clicks and keyboard navigation stay in sync

= 2.6.1 =
* Fix unregistered wp-admin-ui dependency breaking Ollie Pro extensions on some WordPress versions
* Fix React render warning in keyboard shortcut registration

= 2.6.0 =
* Generalize MCP abilities to support posts, pages, and custom post types — not just pages
* Merge create-page and manage-pages into a unified manage-posts ability with create_from_pattern action
* Add from-scratch design layer to skill system for custom pattern creation
* Restructure skill files into modular architecture for better context efficiency
* Fix Ollie icons failing to load due to webpack module ID mismatch between dev and prod builds
* Remove unused collection key button

= 2.5.6 =
* Add responsive max-width control for paragraph and heading blocks in the Dimensions panel
* Fix AI Content popover closing on submit in Safari and Firefox

= 2.5.5 =
* Add new Writing Prompt block — generate fresh content from a prompt using your connected AI provider, with attachable page and file context for tone matching
* Rewrite content using the toolbar Edit with AI button
* Add Ollie AI Tools panel to Extensions page with tutorial video
* Fix Ollie icon library not appearing in the Icon Block library modal

= 2.5.4 =
* Add new "Fade In Words" animation that fades words in one after another for headings and paragraphs, with a Word Delay control to tune the stagger
* Add animation support for paragraph blocks
* Fix editor selection outline jumping when animations are applied to a block

= 2.5.3 =
* Fix responsive font size picker collapsing to a single toggle button when a custom font size is added via Global Styles

= 2.5.2 =
* Add Preferences toggle to disable WordPress 7.0's content-only mode for unsynced patterns (Editor → Preferences → General → Ollie)
* Fix phantom border appearing on buttons with hover border color on WordPress 7.0

= 2.5.1 =
* Add inline pattern prompt block for adding patterns with AI search
* Fix bug where opening the color palette wizard step would reset wp_global_styles, wiping custom color settings without user action

= 2.5.0 =
* Add Ollie Network Settings page for managing Ollie Pro across WordPress Multisite networks
* Add custom SVG icon support for the button icons extension
* Add animation support for post template blocks (sequential fade-in on query loop posts)

= 2.4.2 =
* Add Ollie MCP video in Extensions page
* Fix copy button in MCP instructions
* Updated intro modal

= 2.4.1 =
* Revert group link improvements temporarily
* Remove unused CSS from editor and dashboard
* Add new Ollie pattern library video in dashboard

= 2.4.0 =
* Add new Discover tab to Ollie Pro pattern library
* Introduce Ollie AI
* Add Ollie MCP
* Add Ollie skill for agents
* Fix display bug with linked groups

= 2.3.4 =
* Add responsive controls for group justification
* Improve linked group feature with usability and accessibility fixes

= 2.3.3 =
* Simplify sidebar navigation in pattern modal
* Improve responsive control defaults

= 2.3.2 =
* Fix responsive controls for custom typography

= 2.3.1 =
* Add responsive controls extension - https://www.youtube.com/watch?v=5Wv_3MfU7ws
* Fix text wrap style bug in editor
* Improve animation panel display
* Add animation shortcut button when hovered
* Fix smart sync on columns
* Updated all NPM packages to the latest stable

= 2.3.0 =
* Add eCommerce starter site
* Add eCommerce pages to setup wizard
* Add new typography option (Geist)
* Add new WooCommerce vide to Ollie Pro dashboard
* Security fixes

= 2.2.9 =
* Add eCommerce collection to starter sites and setup wizard

= 2.2.8 =
* Add tooltip to dynamic pattern button
* Fix: pagination calculation in collections

= 2.2.7 =
* Add cover video modal feature
* Add button video modal feature
* Add cover background zoom animation
* Add text wrap options on paragraph and heading
* Add extensions search bar in Ollie dashboard
* Add eCommerce badges and eCommerce payments icons
* Add WooCommerce category image cover block

= 2.2.6 =
* Improve performance on Smart Sync
* Add toggle on extensions screen to disable Ollie Pro pattern library

= 2.2.5 =
* Add Smart Sync extension — style one block and instantly apply those changes to all similar blocks in your layout. Learn about Smart Sync here: https://youtu.be/0OHoSuj4Mcw

= 2.2.4 =
* Add a curated icon library (966 icons) to the Icon Block plugin, displayed under the “Ollie” tab
* Add a new setting to the Cover block that allows users to stretch the inner container to fill the full height of the cover block
* Improve Class Manager performance by reducing requests to a single request
* Fix various browser console messages in the Ollie Pro dashboard

= 2.2.3 =
* Add button icon support to new <button> element
* Add additional settings to child theme generator to allow users to disable Ollie styles, typography, colors, and patterns in their child theme
* Add clickable group support to the cover block, allowing users to add links to the cover block that are triggered on click

= 2.2.2 =
* cache busting + improve ref handling to avoid localStorage cache

= 2.2.1 =
* increased selectable pages to 100 (onboarding)
* fix: make Go Back deterministic in single pattern preview; preserve history

= 2.2.0 =
* removed quick inserter component + improved insertion flow
* refactored search (full-text, debouncer, pagination, performance)
* global availability for toolbar button (including site editor)
* upgraded UpdateChecker to 5.6 for PHP 8.4 support
* WP tested up to 6.9

= 2.1.8 =
* add fix to support Class Manager in synced patterns 

= 2.1.7 =
* fix reduce motion display bug with sequential animations
* make class loading in Class Manager modal much faster
* only show Class Manager on supported blocks

= 2.1.6 =
* introduced class manager extension
* introduced state buttons (advanced group + animation)
* Ollie dashboard redesign
* improved starter site imports

= 2.1.5 =
* add body overflow fix for dropdown items
* add css reset to prevent style cascading into dropdown menus

= 2.1.4 =
* overflow fix for sticky headers
* refactored Menu Designer extension handling
* fix mobile menu when using sticky scroll
* added Menu Designer to setup wizard

= 2.1.3 =
* fixed loader for button icon extension

= 2.1.2 =
* refactored extension loading behaviour
* refactored menu-designer extension handling
* updated dependencies for extensions
* improved asset loading for extensions

= 2.1.1 =
* fixed version number

= 2.1.0 =
* add block editor extensions (animations, grid controls, group controls, button icons, etc.)
* add Extensions page in Appearance → Ollie to enable, disable, and learn about Pro extensions
* view extensions video tutorial here: https://youtu.be/qq2DLc43pTk


= 2.0.5 =
* feature: keep me signed in
* keypress support for login
* reworked how we're handling auth across onboarding and blocks

= 2.0.4 =
* more translation fine-tuning
* automated changelog extraction

= 2.0.3 =
* 100% translatable
* German translation added
* avoid setting default settings on reset

= 2.0.2 =
* set preview posts to drafts when setting up wizard
* check for post type when adding toolbar icon
* only update color if wizard setting is changed

= 2.0.1 =
* new onboarding experience
* starter sites
* new child theme generator
* updated PuC version
* updated dependencies for latest WP version
* improved translation handling + POT file
* fixed onboarding modal
* improved SVG handling
* improved script handling for better performance and cache busting
* fixed admin notice on child theme setups


= 1.9.1 =
* added fix to ensure pattern download in create pages step

= 1.9 =
* add new Ollie Pro dashboard
* add new Ollie Pro site wizard and starter sites
* plugin-wide code cleanup

= 1.3.2 =
* bugfix: modified color palettes path in onboarding

= 1.3.1 =
* updated dependencies for onboarding
* fixed styling related issue due to core changes in onboarding

= 1.3.0 =
* removed search functionality in quick inserter
* added shortcut (CTRL + ^+ O) to open the library
* added a toolbar button to open the library
* modal opens now automatically once the Ollie Pattern Block is inserted
* updated to the latest WP Core dependencies and replaced deprecated components
* added pagination + total pattern count to the library
* refactored favorites to work local-first (stored in WP)

= 1.2.9 =
* added full-text search support (content + title)
* added boolean search support (AND, OR, NOT)
* added the ability to authenticate via wp-config.php (OLLIE_EMAIL + OLLIE_PASSWORD)
* reduced number of NPM packages used for better efficiency

= 1.2.8 =
* fixed full page pattern insertion (with replaceBlocks API)

= 1.2.7 =
* fixed image uploads in Classic Editor
* ability to use the Ollie Pattern Block when editing pages in site editor mode

= 1.2.6 =
* automatically create pattern directory if it doesn't exist when using a child theme

= 1.2.5 =
* fixed downloading and saving patterns in child theme context
* improved category output when downloading themes including ollie namespace
* added support for downloading dynamic pattern versions
* improved downloaded patterns state management

= 1.2.4 =
* fixed customizer image upload when using Ollie Pro
* fixed theme.json overwrites when using child theme with modified theme.json
* improved activation handling and improved query checkup
* default search requests to be lowercase (ongoing improvement)

= 1.2.3 =
* improved asset loading for onboarding and pattern block
* fixed pattern block appearance in site editor context (including template views)

= 1.2.2 =
* account: fallback solution for heavily cached environments (Siteground, InstaWP, WP Engine..)
* implemented nested filtering (downloaded and favorites) inside categories and collections
* improved search behavior (no results) when in collection or category view
* compatibility with WP 6.6

= 1.2.1 =
* improved image loading styles
* fixed search (no results) while in collection or category view

= 1.2 =
* refactored release process
* replaced iframes with automatically generated screenshots for grid view
* fixed permission issue with favorites
* fix hover issue when using Firefox
* removed pattern guide from modal
* fix for line wrapping (headings) + site logo width
* added clear sort button
* improved context logic for quick search (less API requests)

= 1.1 =
* improved login/logout flow
* fixed namespace for patterns when using onboarding
* added quick links to plugin in Plugins settings page
* added logic to show content based on login status
* merged Ollie Account and Dashboard for better UX
* customized wording and UI based on login status
* limited the number of pages for selecting the homepage to parent and max 25 pages
* fixed issue where account area wasn't showing up when account is cancelled
* improved search: No results message + dynamic placeholder based on context (collection, category..)

= 1.0 =
* Initial release
