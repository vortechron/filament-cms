# How CMS platforms handle repeating content (lists) without breaking the design

Researched 2 November 2026 from official documentation only. Every claim has its source URL.
"Unconfirmed" means I could not find it in a primary source. "Inference" means it is my own
conclusion from the sources, not something a platform states.

Purpose: design a small CMS package for Filament 5 (Laravel). Developers hand-design the pages.
Clients edit the content. The hard case is repeating lists (pricing packages, products, FAQs,
team, opening hours).

## 0. The rule (answer to question 5)

This rule is an inference. No platform states it in these words, but all four platforms split
content the same way (evidence in section 5).

Ask three questions about the list, in this order:

1. **One value set for the whole site?** (phone, address, social links, footer text, opening
   hours shown in the footer and on the contact page) -> **global settings**.
2. **Does each item need its own page or URL, or appear on more than one page, or need
   filtering, search, publish dates, or dozens of rows?** (products, blog posts, team members
   with bio pages) -> **collection / record type** (its own table or model).
3. **Otherwise, is the list part of one page or one section, ordered by the editor, with no URL
   of its own?** (the 3 pricing packages on the Pricing section, the FAQ on one page) ->
   **repeater / blocks field stored on the page**.

Quick test: "If I delete this page, should the items go too?" Yes -> repeater. "Do the items have
their own URL, or show up on 2 or more pages?" -> collection. "Is there exactly one of it for the
whole site?" -> global.

## 1. Shopify Online Store 2.0

Shopify docs are undated. Everything below is what the pages said when fetched on 2 November 2026.

### 1.1 Sections and blocks

- A section is a Liquid file in the theme's `sections` directory. Sections can include blocks,
  which merchants use to add, remove and reorder content inside the section.
  https://shopify.dev/docs/storefronts/themes/architecture/sections
- Sections are placed "dynamically" through JSON templates or section groups, or "statically" with
  the Liquid `section` tag. The docs say: "Statically rendered sections can't be removed or
  reordered by merchants." https://shopify.dev/docs/storefronts/themes/architecture/sections
- A section must define `presets` to be added in the theme editor. A section without presets must
  be added to the JSON file by hand and cannot be removed in the editor.
  https://shopify.dev/docs/storefronts/themes/architecture/templates/json-templates
- Section `{% schema %}` attributes: `name`, `tag`, `class`, `limit`, `settings`, `blocks`,
  `max_blocks`, `presets`, `default`, `locales`, `enabled_on` / `disabled_on`. The schema must be
  valid JSON only. https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema
- `blocks` entries: `type` (required, free-form id), `name` (required, shown in the editor),
  `limit` (optional, how many of this block type), `settings` (optional). Block types and names
  must be unique inside a section. The block title in the editor comes from a setting with id
  `heading`, `title` or `text` (checked in that order), else from `name`.
  https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema
- Limit: "There's a limit of 50 blocks per section." `max_blocks` can lower it (the doc example
  uses 5). Static blocks do not count toward the limit.
  https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema
- `presets` appear in the "Add section" picker. A preset has `name` (required), `category`,
  `settings` and `blocks` (the default blocks). The doc example is a Slideshow preset that starts
  with two `slide` blocks. This is how a developer can start a pricing section with 3 cards.
  https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema
- Including `{ "type": "@theme" }` in `blocks` together with named block types marks those as
  "recommended blocks" in the picker; other theme blocks stay behind "Show all".
  https://shopify.dev/docs/storefronts/themes/architecture/sections/section-schema
- `@app` block details: not covered on the schema page (unconfirmed there).

### 1.2 Section blocks vs theme blocks (newer)

- Shopify now describes three block kinds: theme blocks, section blocks, app blocks.
  https://shopify.dev/docs/storefronts/themes/architecture/blocks
- Theme blocks are separate Liquid files in the `/blocks` folder, reusable by any section, and can
  nest. "A theme can contain at most 300 theme blocks." The page has no date or version.
  https://shopify.dev/docs/storefronts/themes/architecture/blocks
- Section blocks are defined in the section's own `{% schema %}`, work only in that section,
  support one level (no nesting), and cannot be used in the same section as theme blocks.
  https://shopify.dev/docs/storefronts/themes/architecture/blocks
- A nesting depth limit and a blocks-per-section limit for theme blocks: not stated on that page
  (unconfirmed). The general 50-blocks-per-section limit is on the section and JSON template pages.
- Eligible merchants can generate a theme block with Sidekick (AI) in the theme editor; it is saved
  to `/blocks` as a normal theme block. Availability is "eligible merchants" only, no dates.
  https://shopify.dev/docs/storefronts/themes/architecture/blocks

### 1.3 Static blocks (the "locked" part of a design)

- Rendered with `{% content_for "block", type: "...", id: "..." %}`. The id is set by the developer
  in Liquid. https://shopify.dev/docs/storefronts/themes/architecture/blocks/theme-blocks/static-blocks
- Dynamic blocks use `{% content_for "blocks" %}` and "Merchants can add, remove, and reorder"
  them. https://shopify.dev/docs/api/liquid/tags/content_for
- Merchants CAN hide a static block and change its settings. They CANNOT reorder, remove or
  duplicate it. Static blocks do not count toward `max_blocks`. They can be rendered conditionally
  or in a for-loop, dynamic blocks cannot.
  https://shopify.dev/docs/storefronts/themes/architecture/blocks/theme-blocks/static-blocks
- Help Center, theme editor: some sections have "required blocks". "You can customize or hide these
  blocks, but you can't re-order or delete them." (Found through search results; the page may be
  older than the developer docs.) https://help.shopify.com/en/manual/online-store/themes/theme-structure/sections-and-blocks

### 1.4 How a merchant adds a block (for example a 4th pricing card)

From the Help Center (page may be older, menu labels may have changed):
1. Open the template in the theme editor.
2. Hover the section and choose "Add block", then pick a block from the list or use "Search blocks".
3. Choose "Save".
- Some sections cap their blocks: when the limit is reached "Add block" is grayed out.
- Reorder: press and hold the drag handle and move the block. A block cannot move to a section
  where its type is not allowed.
- Hide: hide icon next to the block name. Delete: delete icon. Duplicate: right-click the block,
  "Duplicate" (the copy goes into the same section).
https://help.shopify.com/en/manual/online-store/themes/theme-structure/sections-and-blocks

### 1.5 Where the data is stored

- Page-level sections and blocks: `templates/*.json` (for example `templates/product.json`). Root
  keys: `sections` (id -> `type`, `settings`, `blocks`, `block_order`, `disabled`) and `order`
  (render order). Section and block ids accept only alphanumeric characters. The doc has a full
  example with a `featured` section holding `blocks` and `block_order`.
  https://shopify.dev/docs/storefronts/themes/architecture/templates/json-templates
- Limits: JSON templates and section groups render up to 25 sections. Each section has up to 50
  blocks. A theme holds up to 1,000 JSON templates.
  https://shopify.dev/docs/storefronts/themes/architecture/templates/json-templates and
  https://shopify.dev/docs/storefronts/themes/architecture/sections
- Theme-wide settings: `config/settings_data.json`. It holds the values for the settings declared in
  `settings_schema.json`. Top-level keys: `current` (values saved in the theme editor), `presets`
  (one object per preset, up to five designs) and `platform_customizations` (written by Shopify).
  https://shopify.dev/docs/storefronts/themes/architecture/config/settings-data-json
- That page does not compare itself with `templates/*.json` or section groups. So "settings_data.json
  holds theme-wide settings, templates hold per-page sections" is my reading (inference).
- Where section groups are stored on disk: unconfirmed (not on the pages fetched).

## 2. Shopify metafields and metaobjects

- Metafields add an extra field to an existing resource (product, order, customer). Metaobjects are
  new record types you define (size chart, store location, ingredient, product review).
  https://shopify.dev/docs/apps/build/custom-data
- Shopify's guidance: use metafields when "you're extending something Shopify already has"; use
  metaobjects when "modeling something new" or "the data doesn't belong to an existing Shopify
  object". Most apps use both: a metaobject defines the record and a metafield attaches it to products.
  https://shopify.dev/docs/apps/build/custom-data
- A metaobject has a definition (the field schema) and entries (the records). One definition can
  have many entries. Entries "can be referenced from multiple resources".
  https://shopify.dev/docs/apps/build/custom-data/metaobjects
- Help Center examples include FAQs (question and answer pairs in a theme section), product
  highlights, and event appearances. "Your metaobject can be referenced by metafields or used
  stand-alone." Definitions are managed in Settings > Custom data. Entries are managed in Content >
  Metaobjects. https://help.shopify.com/en/manual/custom-data/metaobjects
- **Unconfirmed:** a Shopify page that says "use metaobjects instead of section blocks when content
  is reused on many pages". The pages I read do not use that wording. The closest statements are
  the ones above ("referenced and reused across your store", "new record types").
- Theme side: a `metaobject_list` section/block setting lets the merchant pick several entries of one
  type from a picker. Attribute `metaobject_type` (required), `limit` (default and maximum 50). In
  Liquid it returns an array of `metaobject` objects, or `blank`. It supports the `paginate` tag and
  `.count`. One type per setting. Themes listed on the Theme Store may use only standard definitions.
  https://shopify.dev/docs/storefronts/themes/architecture/settings/input-settings
- Reading one entry: `{{ metaobjects.type.handle }}` and field values like
  `{{ metaobjects['highlights']['washable'].image.value }}`; in a metaobject template the entry is
  `metaobject`. If the `publishable` capability is on, a draft entry returns nil.
  https://shopify.dev/docs/api/liquid/objects/metaobject
- A complete official Liquid `for` loop over a `metaobject_list` setting: unconfirmed. The official
  input-settings page has no loop example. A Shopify Partners post says a loop over a definition's
  entries returns the first 50 by default and recommends `paginate` beyond that, but its code
  snippets did not come through when fetched.
  https://www.shopify.com/partners/blog/how-to-create-an-index-list-for-metaobject-pages
- Dynamic sources let a merchant connect a section/block setting to a metafield or a metaobject
  field in the theme editor. They are not available for general theme settings. For a
  `list.metaobject_reference` metafield, "the metaobject entries attached to the resource are
  iterated over". https://shopify.dev/docs/storefronts/themes/architecture/settings/dynamic-sources

## 3. WordPress

### 3a. Block editor (Gutenberg): patterns and locking

- A **block template** is a list of blocks for a post type. Set it with the `template` argument of
  `register_post_type()` or on `$post_type_object->template` at `init`.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/
- `template_lock` values on the post type: `all` (cannot insert, move or delete blocks), `insert`
  (cannot insert or remove, but can move), `contentOnly` (prevents all operations; "not
  overridable by children"). https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/
- Per-block `lock` attribute: `remove` (locks removal) and `move` (locks moving). A block-level lock
  takes priority over `templateLock`. Setting `move` or `remove` to `false` on one block leaves that
  block editable under a template-wide lock. Inside `InnerBlocks`, if `templateLock` is not set, the
  parent's lock is used. Page last modified 14 October 2026 (no WordPress version named).
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/
- `contentOnly` in practice: editors can edit the content of blocks. Non-content child blocks
  (containers, spacers, columns) are hidden from List View and cannot be clicked or edited.
  Extra child blocks cannot be inserted. Children are move/remove locked automatically. It works for
  Columns, Cover and Group blocks. There is no UI to set it; it is set in code
  (`"templateLock":"contentOnly"`). "Content-only editing was introduced in WordPress 6.1" (as
  summarised from the page). By default, users with editing access can unlock these blocks. Use the
  `block_editor_settings_all` filter and `canLockBlocks` (for example
  `current_user_can( 'edit_theme_options' )`) to control who may lock or unlock.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-locking/
- The locking page does not list the `all` and `insert` values or the `lock` attribute keys; the
  block-templates page above does.
- **Block patterns** are predefined block layouts shown in the Patterns tab of the inserter. A theme
  or plugin registers one with `register_block_pattern( 'namespace/title', [ 'title' => ..., 'content' => ... ] )`
  at `init`. After insertion the user edits content and settings.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/
- WordPress 7.0 (post dated 15 March 2026): unsynced patterns and template parts now default to
  `contentOnly` mode. Block authors mark attributes with `role: content` so editors can change them
  in this mode; blocks with no such attribute are hidden from List View. A site setting
  `disableContentOnlyForUnsyncedPatterns` turns this off. Synced patterns and template parts are
  unaffected.
  https://make.wordpress.org/core/2026/03/15/pattern-editing-in-wordpress-7-0/
- The `patterns/` folder in a block theme and its header comment fields: unconfirmed (the theme
  handbook chapter page I fetched is only an overview).
  https://developer.wordpress.org/themes/patterns/
- Inference: core WordPress has no "repeater" field. A repeating list in core is a group of
  blocks inside a locked container, which the editor can extend only if the lock allows inserting.
  The sources above do not say this in so many words.

### 3b. Custom post types (repeatable records)

- WordPress ships five default types (`post`, `page`, `attachment`, `revision`, `menu`). You register
  your own with `register_post_type()` (for example products, assignments, movies). The new type
  gets its own top-level admin menu and its own URL slug (changeable with `rewrite`).
  https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
- The handbook recommends putting custom post types in a plugin, not a theme, so content survives a
  theme change. https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
- A theme reads them with `new WP_Query( [ 'post_type' => 'product', 'posts_per_page' => 10 ] )`.
  Template files: `single-{post_type}.php` and `archive-{post_type}.php`. A registered type is not in
  the main query automatically (use `pre_get_posts`).
  https://developer.wordpress.org/plugins/post-types/working-with-custom-post-types/

### 3c. Advanced Custom Fields (ACF): Repeater and Flexible Content

- **Repeater**: repeats a group of sub fields (slides, team members, CTA tiles). Each repeat is a
  row. Settings: minimum and maximum rows, layout (table, block, row), collapsed, button label,
  pagination (added in ACF 6.0, admin only, off by default). The theme loops with `have_rows()`,
  `the_row()`, `get_sub_field()`, or gets an array with `get_field()`.
  https://www.advancedcustomfields.com/resources/repeater/
- **Flexible Content**: "a simple, structured, block-based editor". The field defines layouts;
  the editor adds rows with an "Add Row" button and picks a layout. Settings: minimum and maximum
  layouts, button label. The theme loops with `have_rows()` and branches on `get_row_layout()`.
  https://www.advancedcustomfields.com/resources/flexible-content/
- Licence: both pages list these fields under "PRO Fields". I did not see a sentence that states
  the licence rule outright, so treat "Repeater and Flexible Content need ACF PRO" as unconfirmed
  from the docs pages themselves.
- How the data is stored: the official Repeater page does not say. ACF's own support forum
  (community answers, not the docs) says the repeater key holds the row count and each sub field is
  saved as `{repeater}_{row}_{subfield}` with rows starting at 0, plus a matching underscore key for
  the field key. Treat as unconfirmed from a primary doc.
  https://support.advancedcustomfields.com/forums/topic/understanding-the-relationship-between-repeating-fields-and-database-entries/
- **Options page** (site-wide settings): data is "global and saved in the `wp_options` table".
  Register with `acf_add_options_page()` inside `acf/init`; read with `get_field( 'x', 'option' )`.
  The Options Pages UI was introduced in ACF PRO 6.2.
  https://www.advancedcustomfields.com/resources/options-page/

## 4. Developer-first CMSs: Statamic and Craft CMS

Both have the same three-way split. Statamic docs mention v6+ features, so they describe a recent
major version (exact version of the whole docs set: unconfirmed). Craft pages are the 5.x docs.

### 4a. Statamic

- **Blueprints** are the schema for content: they define which fields appear in the editor and the
  data shape for the front end. Files are YAML in `resources/blueprints/`. A collection can have
  several blueprints (each entry picks one). Globals, asset containers and forms get exactly one
  blueprint per item. https://statamic.dev/blueprints
- **Collections** are "containers that hold groups of related entries" (blog posts, products,
  recipes). Entries get URLs from the collection `route` (for example `/blog/{slug}`). Stored as
  Markdown files in `content/collections` (the page does not mention a database option). An
  "Orderable" switch adds drag-and-drop ordering. https://statamic.dev/collections
- **Replicator** (page-level, mixed sets): named sets, each with its own fields. "You will be
  presented with a button for each set you've defined." The editor can add the same set many times
  and drag to reorder, and can collapse sets. Stored on the entry as an array where each item has a
  `type` (the set name). Templates loop and branch on `type`; one partial per set is suggested. It
  cannot be used for the `content` field. Options such as a maximum number of sets or a button
  label: unconfirmed (not on the page I fetched). https://statamic.dev/fieldtypes/replicator
- **Grid** (page-level, same columns on every row): "repeating rows of data", each row has the
  same set of columns, stored as a YAML list. Template: loop the field and use column names as
  variables. The page warns "because you can doesn't mean you should" about nesting any fieldtype.
  `min_rows`, `max_rows` and similar options: unconfirmed (not on the page). The page does not say
  when to pick Grid over Replicator, only to compare the editing experience.
  https://statamic.dev/fieldtypes/grid
- **Globals** store content that belongs to the whole site, "available everywhere, in all of your
  views". Grouped into global sets (contact details, footer, site settings). Values are stored per
  site in `content/globals/{site}/{handle}.yaml`. Blueprint is optional.
  https://statamic.dev/globals

### 4b. Craft CMS (5.x)

- Everything is an **element**: entries, assets, users, global sets, categories, tags, addresses.
  https://craftcms.com/docs/5.x/system/elements.html
- **Sections** have three types. Singles: "one-off pages". Channels: lists or streams of similar
  content (blog posts, reviews). Structures: channels plus an explicit hierarchy that authors drag
  into a tree. Entries in a Structure can be moved to new parents, "unlike nested Matrix entries,
  which always belong to their owner element."
  https://craftcms.com/docs/5.x/reference/element-types/entries.html
- **Matrix field** (page-level repeater): holds nested entries "owned" by the element the field is
  on. In Craft 5 "Matrix blocks became entries". The author clicks a button per entry type (a menu if
  there are several), drags the handle to reorder. Settings: Min Entries, Max Entries (default no
  limit), View Mode (Cards, Card grid, Blocks, Index), and the "New" button label. Template:
  `{% for nestedEntry in entry.myFieldHandle.all() %}` and branch on `nestedEntry.type.handle`.
  Some settings are version-tagged: card grid view 5.9.0+, entry type groups 5.8.0+, nested entry
  versioning 5.7.0+. https://craftcms.com/docs/5.x/reference/field-types/matrix.html
- **Global sets**: "Globals store content that is available globally throughout your templates but
  is not tied to any one URL." Each set has its own field layout and is reachable from any
  template by handle (for example `{{ companyInfo.yearEstablished }}`). Values can differ per site.
  No Live Preview. "Craft 4.4 began consolidating globals into entries"; a command converts a
  set to a single entry. https://craftcms.com/docs/5.x/reference/element-types/globals.html
- Singles "work like globals" (the page says they can be preloaded into Twig).
  https://craftcms.com/docs/5.x/reference/element-types/entries.html

## 5. The common pattern across all four

| Need | Shopify | WordPress | Statamic | Craft 5 |
|---|---|---|---|---|
| (a) Repeater on the page | Section blocks, saved in `templates/*.json` | Blocks in a locked container; ACF Repeater / Flexible Content (PRO listing) | Replicator (mixed sets), Grid (same columns) | Matrix field (nested entries owned by the page) |
| (b) Separate record type | Metaobject definition + entries | Custom post type | Collection + entries | Channel or Structure section |
| (c) Global settings | `config/settings_data.json`, theme settings | ACF Options page (`wp_options`) | Globals | Global sets |

What the sources say about why each kind exists:

- (a) Page-level: the items are owned by one page or section. Craft: nested Matrix entries "always
  belong to their owner element". Shopify: section blocks work only in the section that defines them.
  Developer keeps design control by choosing which block types exist, setting caps (Shopify
  `max_blocks`, ACF max rows, Craft Max Entries) and templates that render each type.
- (b) Separate record: the item has its own identity. Shopify metaobjects are "new record types"
  that "can be referenced from multiple resources". WordPress custom post types get their own
  admin menu, URL and archive. Statamic and Craft collections/channels give each entry a URL and
  ordering.
- (c) Global: one set of values for the whole site. Statamic globals are "available everywhere".
  Craft globals are "not tied to any one URL". ACF options data is "global".

## 6. What this means for the Filament 5 package (inference, not researched in Filament docs)

- Section "page-level repeater": a JSON column on the page row, edited by a Filament repeater or
  builder. The developer fixes the item fields, a max count, and the Blade loop. This mirrors
  Shopify section blocks with `max_blocks`, Statamic Grid, Craft Matrix.
- Section "collection": a normal Eloquent model plus a Filament resource. Use it when items need
  their own page, appear on many pages, or number in the dozens.
- Section "global": one settings record (phone, address, footer, social links, shared opening hours).
- Locking idea from Shopify static blocks and WordPress `contentOnly`: let the developer mark some
  elements as fixed (client can edit text, cannot move or delete). This is how both platforms
  protect the design.
- I did not check Filament 5 docs for repeater, builder or settings support. Verify before building.

## 7. Gaps (could not confirm from a primary source)

- A Shopify page that says "use metaobjects, not section blocks, for content reused on many pages".
- An official Shopify Liquid `for` loop over a `metaobject_list` setting.
- Shopify theme block nesting depth limit, and where section groups are stored on disk.
- ACF Repeater database storage layout (only found on ACF's community support forum).
- The explicit licence sentence for ACF Repeater / Flexible Content (they are listed as PRO fields).
- WordPress block-theme `patterns/` folder header fields.
- Statamic Replicator and Grid option names (max/min counts, button label).
- A community thread says Shopify theme blocks lack a repeater for metaobject lists. That is a forum
  post, not a primary source: https://community.shopify.dev/t/missing-repeater-functionality-on-theme-blocks/10253
