# Page format 2: deviations of track C1 (CMS core)

The contract is `docs/v2-plan.md` in `vortechron/filament-block-editor`. This file lists where tracks C1 to C4
chose differently or added detail, and why. 3 November 2026. The numbers run through the whole file (C1 1 to 20,
C2 21 to 33, C3 34 to 49, C4 50 to 67, the review fixes from 68). Before the review fixes C2 started again at 1,
and C3 and C4 both started at 20.

## Shapes other tracks read

1. **Parts carry `class="sf-page"`.** `$header` and `$footer` are `<div data-part="header" class="sf-page">…</div>`.
   A project prints them outside its main `.sf-page`, and the page styles, the kit variables and the
   `.sf-page .fbe-s-{id}` style rules only work inside one.
2. **The header and footer builder preview.** The part being edited is drawn for the builder in
   `<div class="sf-page" data-preview>` with no `data-part`, so its sections stay editable. The home page's
   content is drawn as visitors see it in `<div data-part="page" class="sf-page">`, and the other part in its
   usual `data-part` wrapper. **Track B2:** sections inside any `[data-part]` (header, footer or page) are not
   sections of the value being built, even though they carry `data-block-id`.
3. **The header and footer are made on first use** when the builder opens `/admin/site-pages/header/build` or
   `/admin/site-pages/footer/build`. `parts[].editUrl` uses these addresses, so listing the parts makes nothing.
   Drawing a page never makes them.
4. **Kinds `section` and `template` hold one rich section or none.** The store refuses more, or a `ref`, with a
   plain error under `sections`. "Bina" opens both on the Seksyen screen; the builder's kind for a saved section
   (`template`) is `section`, as the builder has no `template` kind.
5. **Saved templates** in the builder's `templates`: key `saved-{id}`, category `saved` (the literal word, for B2
   to label), description `''`. `saveSection` returns `template: null` for a synced section.
6. **`savePageBuilder` returns the store's refusal** as `['ok' => false, 'errors' => [...]]` instead of throwing.

## Who is a developer

7. **No `developers` closure in config.** Closures break `php artisan config:cache`. `DocumentStore::save()` takes
   `?bool $developer`; null asks the current panel's `FilamentCmsPlugin::isDeveloper()`, and is false outside a
   panel. MCP (track C4) passes `developer: true`.

## Dynamic values

8. **`site.whatsapp_number` is type `text`, not `link`.** It is digits such as `60123456789`; a link field would drop
   it. The form's WhatsApp action reads it by key, so its type does not matter there.
9. **`site.phone_link`** is `tel:+` and the same international digits as WhatsApp (`012-345 6789` →
   `tel:+60123456789`). **`site.hours`** joins its lines with a newline. Social keys follow
   `SocialSettings::PLATFORMS` (`site.facebook`, `site.instagram`, `site.tiktok`, `site.threads`, `site.x`,
   `site.youtube`, `site.shopee`, `site.lazada`); each is the first link of that platform.
10. **`page.title` and `page.url`** come from the RenderContext values `SitePageRenderer` passes. Their registered
    resolvers return the context value. The builder shows them through the block editor's
    `getPageBuilderRenderValues()` hook: the page itself, an entry's own page for an entry body or a single design
    (the type's first entry), else the home page. `document.id` is left out of the render values.
11. Labels and groups are translated when `DynamicValues` is first resolved, in that request's locale.

## Shop info

12. **Four hooks on `SettingsSection`, not one.** `builderFields()` as the contract says, plus `builderRules()`
    (block editor fields have no validation rules, and the save must check like the Tetapan screen),
    `toBuilderValues()` and `fromBuilderValues()` (Tetapan saves phones as a plain list, the builder's list holds
    rows `['number' => ...]`).
13. **A save checks the stored values with the sent ones on top**, then cleans with `Field::cleanFields`. A field
    the builder did not send keeps its stored value, not the field default. Empty text is saved as null, as
    Tetapan does.
14. **Pictures (the logo) are paths on the CMS disk.** The builder gets their address. A new picture must be a file
    that exists on `filament-cms.disk`, directly inside `CmsImage::DIRECTORY` (`cms`) or the builder's upload
    folder (`filament-block-editor.directory`, only when the builder uploads to the same disk), with a plain name
    and one of CmsImage's types (jpg, jpeg, png, webp, gif; no svg). Nothing in the address is decoded, so `..`,
    `%2e` and `//` never pass. Anything else is refused with "Choose a JPG, PNG, WebP or GIF picture uploaded
    here." (security review, 3 November 2026).

## Screens and options

15. **`parts(false)`**: no Header & footer area, `parts` is empty, no `siteInfo`, and `saveSiteInfo` is not offered.
    Pages still draw header and footer documents that already exist (drawing has no panel to ask).
16. **`sections(false)`**: no Seksyen screen, no `saveSection`, no synced sections and no saved templates in the
    builder. The Seksyen screen also needs `sitePages()` on, because the builder page belongs to the Halaman resource.
17. **Access.** For a page the builder asks `SitePageResource::canEdit($page)`. For every other kind, and for the
    Seksyen screen, it asks the same with an unsaved `SitePage`, so a SitePage policy covers all of them.
18. **`SitePageStore` now throws a `ValidationException`** when the store refuses a value. Before, it cleaned
    silently.
19. **Paths.** A document's `path` may be null; `SitePage` still turns a missing path into `/`.
20. **The documents migration rewrites every page's content with `PageValue::normalize()`**, not only
    `upgrade()`. So after it runs, every stored page is a clean format 2 value, the same shape the store saves, and
    code that reads `content` sees one shape. Reads still upgrade (`Document::contentSections()`,
    `PageValue::normalize()` in the builder), so a row written another way (an old backup, a raw insert) still
    draws. The down migration writes the cleaned sections back as a format 1 list.

# C2 (CMS forms), 3 November 2026

## Where a form posts and how it is found

21. **`document.id` in the RenderContext values** (`CmsFormEndpoint::VALUE`). `SitePageRenderer::pageValues()` adds it
    for a saved page, and the header and footer get the same values, so their forms post to the page they are on.
    **Track C3:** put `document.id` (the entry's id) in the values when drawing an entry page. `hiddenFields()` adds
    nothing. Without the value, or without `FilamentCms::routes()`, the form has no endpoint.
22. **The route takes any id** and the controller answers 404 for one that is not a number. With `whereNumber()`
    the GET catch-all answered such a POST with 405.
23. **Where the stored form is looked for**, in this order: the document's own sections, an entry's type `single`
    document (C3 draws it first), the header, the footer, then the synced sections any of them shows (by the
    `ref` section's id, one level). Only sections visitors see count (hidden ones are skipped). A key with no
    section id (`{blockId}`) is looked for in all of them. A key over 100 characters or a form that is not there
    is a 404. **Known limit:** a form inside an entry card drawn by a list block is never looked for. So cards are
    drawn without `document.id` (`EntryList::cardValues()`): such a form has no endpoint, its button is disabled,
    and the card's builder shows the "not connected" note, instead of a visitor sending into a 404 and losing the
    message.

## Checks

24. **Quiet refusals go to the page's `#form-{blockId}`**, not to the Referer, with no flash and no error.
25. **The rate limit counts every send that passes the bot checks, good or not**, keyed by the IP and the block id,
    so a header form has one limit on every page. Its "Cuba lagi sebentar" shows under the form's first field
    (`form.{blockId}.{firstField}`): the form block only shows errors by field.
26. **Validation messages are the package's own** (`filament-cms::forms.validation`, Malay and English), with the
    field labels as names, so a Malay site needs no `lang/ms/validation.php`.

## What is saved

27. `data` has one value per stored field, in the form's order: text as a string or null, checkboxes as a list,
    consent as true or false. `form_name` is null when the form has no name. `meta` is `page` (the document path),
    `user_agent` (cut to 300 characters) and `ip_hash` (HMAC-SHA256 of the IP with the app key).

## Actions

28. **Email:** skipped (with a log line) when the address is empty or not an email, or `mail.default` is `log` or
    `array`. Reply-To is the first email field the visitor filled. An empty subject is "Borang baru: {name}". HTML
    only, every value escaped.
29. **Webhook** (security review, 3 November 2026): JSON `{id, form, page, data, sent_at}`. The host must be a plain
    ASCII name (no trailing dot, the last part starting with a letter, so `2130706433` and `127.1` are refused) or
    an IP address written in full. Every address must be public: also refused are carrier-grade NAT (`100.64.0.0/10`,
    which Tailscale uses), multicast, and the IPv6 forms of private IPv4 addresses (mapped, NAT64, 6to4). curl
    connects to the checked address for any host and port (`CURLOPT_CONNECT_TO`), through Guzzle's `CurlHandler`,
    with `proxy` `''` (no proxy from the environment) and no redirects. Every address curl reports
    (`CURLINFO_PRIMARY_IP`, read in `on_stats`) must be the checked one, or the send failed. Without the curl
    extension the webhook is refused. Logs name the host only, never the link.
30. **WhatsApp:** the number goes through `cms_whatsapp_number()` (a leading 0 becomes 60) and must be 8 to 15
    digits, else the action is skipped. A `{name}` the form has no field for stays as written. Lists are joined
    with ", ", ticks are "Ya" or "Tidak". An empty message lists every field as "Label: value". The message is cut
    to 2000 characters. WhatsApp wins over redirect.
31. **Redirect:** only a `/path` on this site or an `http(s)://` link. A `mailto:`, `tel:` or `#` link is no place to
    send a visitor, so they go back to the form.
32. **The success flash is set for WhatsApp and redirect too**, so a visitor who comes back sees the message.

## The Borang screen

33. **In "Kandungan" with sort 99**: after Halaman (-10), Seksyen (-9), the entry screens (-8) and the editable pages
    (from 0), before Tetapan (100), so the owner lands on Halaman after login. (It used to be top level, above
    Halaman.) The form filter is by `form_key`, so two forms with the same name stay apart and a form with no name
    can be picked; each option is the form's newest name and the pages it was sent from, newest first ("Borang
    pertanyaan · /hubungi"). "Mark as new" is labelled "Tanda belum dibaca" (Mark as
    unread). Labels are found in hidden sections too, so an older submission keeps them. The CSV is our own
    streamed file, not Filament's exporter (that one needs a queue and its own tables): UTF-8 with a byte order
    mark, one column per field name seen in the filtered rows, and a `'` before a value a spreadsheet would run as
    a formula.

## C3: entries

Track C3 (entries), 3 November 2026.

### Shapes and names

34. **Classes.** `Entries\EntryType` (the definition) and `Entries\EntryTypes` (the singleton registry, with
    `register`, `forget`, `has`, `get`, `all`). `Models\Entry` is kind `entry` (a global scope, like `SitePage`), so a
    host writes an `EntryPolicy` for it. `Entries\EntryStore::save(Entry, $data, $user, ?$developer)` saves the
    title, slug, publish switch, `sort` (optional; a new entry without one goes last) and content in one save; the
    content part goes through `DocumentStore`.
35. **Field names** must match `[a-z][a-z0-9_]*` (stricter than `Field`), so each one is a valid dynamic value key
    `entry.{name}`. A name given twice is refused. A type path may not start with one of
    `FilamentCms::RESERVED_PATHS`, the same list the page route leaves alone (`up`, `livewire`, `admin`, `storage`,
    `filament`, `css`, `js`, `fonts`, `vendor`, `mcp`, `_cms`). Two types may not share a path.
36. **`DocumentStore` for kind `entry`** takes `fields` and/or `sections` (a bare list counts as sections). The part
    not sent keeps its stored value, so the page builder (sections only) and the entry form (fields only) never
    erase each other. An unregistered `entry_type` is refused (`entry_type`), `fields` that is not an array is refused
    (`fields`). Sections sent for a type without a body are dropped, not refused (the MCP server refuses them,
    deviation 65). Restoring a version of an entry brings back its sections and keeps the stored fields.
37. **Slug.** No slug column: the slug is the last part of `path`. A new entry's slug comes from its title (the
    type key when the title has no letters), at most 80 characters. It stays when the title changes; a given slug
    replaces it, an empty one makes it from the title again. "Unique within the type" became **unique among all
    documents**, because pages and entries share the unique `path` column. For the same reason the Halaman form now
    refuses a page address an entry already has (it used to check pages only, and would have hit the index).
38. **A type path changed in code** moves each entry when it is next saved. Until then the old address keeps
    working. A type changed to `path(null)` (or removed) shows no entry pages at all.
39. **Image fields** store a public address. The form's upload field works with paths on the CMS disk; a submitted
    path is kept only when it is a file of CmsImage's types directly inside `cms/` (the same rule as the logo,
    deviation 14). A stored picture from elsewhere (an MCP save of an `https://` address) is kept when the form sends
    none, because the upload field can not show it; inside a list it is dropped on the next form save.

### Screens

40. **One resource, one page class**: `EntryResource` with the page `ManageEntries` at `/admin/entries/{type}`.
    The resource returns one navigation item per type (`getNavigationItems()`), sort -8 in "Kandungan". Create and
    edit open in a modal, as on the Seksyen screen. Publish and unpublish are row actions that save through
    `EntryStore`; the list shows "Diterbitkan" as an icon.
41. **"Reka kad" and "Reka halaman entri"** are actions that make the design on first use (a Livewire call, not a
    GET) and redirect to `BuildSitePage`. "Reka halaman entri" shows only for a type with a path. Both need the
    Halaman screen on and follow the SitePage rule (deviation 17). An entry's body follows the Entry policy
    (`EntryResource::canEdit($entry)`).
42. **The builder for an entry** opens only for a registered type with `body(true)` while entries are on, else 404.
    Its `kind` is `page`, `viewUrl` is the entry's page when published, and every entry, card and single goes back
    to the type's screen. With `entries(false)` the builder does not offer the `cms-entries` block.

### Dynamic values and drawing

43. **`entry.*` values**: text, textarea and number fields are text; a select is its option's **label**; link and
    image fields are links and pictures (a stored `@key` is followed one level, then checked). Toggles and lists are
    not values. `entry.url` is null for a type without a path. Labels: "Tajuk" / "Alamat entri", then each field's
    label; the group is the type's label.
44. **Per-builder values.** `BuildSitePage` uses the block editor's hooks: `getPageBuilderDynamicValues()` gives
    the type's `entry.*` definitions (without previews), and `getPageBuilderRenderValues()` the `page.*` values of
    the page it is shown on and the `entry.*` values of the entry it shows (deviation 10). The builder reads each
    preview from the render values. (Until the block editor had these hooks, `BuildSitePage` appended the
    definitions in `getPageBuilderOptions()`.)
45. **Previews.** A card or single is drawn with the type's first entry (`Entry::firstOf()`: published ones first,
    in the type's order); a single with that entry's `page.*` values too, as on its page. An entry body's preview
    is its real page: the single design as visitors see it, inside `<div data-part="page" class="sf-page">`, then
    the editable body.
46. **Sections that are not the edited value carry `data-entry-block-id`** in the builder (cards in a list block,
    the single design above a body), not `data-block-id`, because the builder's canvas, drag and drop and swaps find
    sections by `data-block-id` anywhere in the frame (`Entries\Inert`). Visitors get the normal wrappers.
47. **The list block** is registered (and re-registered with the new type options) by `EntryTypes::register()` and
    forgotten when the last type goes, so it works whatever order the providers boot in. Its labels are translated
    then. `order` defaults to `newest`, `columns` to `3`, `limit` to 6. A stored type that is no longer registered
    falls back to the first type (a select field's rule). Cards are drawn as visitors see them. A list inside a card,
    also in the card's builder preview, draws nothing (a note in the builder). A designed card's style rules are
    added once per card drawn (the style sheet does not merge repeats).
48. **The plain card and page** use the design kit's variables in `resources/css/entries.css`, printed inline by
    `<x-filament-cms::entries-styles />`, instead of borrowing core block classes from the block editor's page CSS.
49. **Entry pages.** `ShowSitePage` looks for a published page first, then a published entry of a registered type
    with a path. The page view gets `$page` (the entry, with `seo_title` = its title and `seo_description` = its first
    textarea, at most 160 characters, set on a copy, not saved), `$sections` (its body), `$content` (design or plain
    layout, then the body), `$header`, `$footer`. `SitePageRenderer` gained `layout()` for this, and
    `preview(..., $values)` for the card and single previews. With `entries(false)` entry pages are still served
    (drawing has no panel to ask).

## C4 — MCP (3 November 2026)

The site's MCP server (`FilamentCms::mcp()`, `Mcp\CmsServer`) on `laravel/mcp` ^1.1 (1.1.2 installed).

### Route and access

50. **Middleware order:** the rate limit (`ThrottleRequests::with(60, 1, 'filament-cms-mcp')`, by IP), then
    `CmsMcpToken`, then laravel/mcp's own. The limit counts before the token check, so token guessing is limited
    too. The GET and DELETE answers (405) sit behind the token as well, so without a token nothing shows the
    address exists.
51. **CSRF:** the route leaves out `PreventRequestForgery` (Laravel 13's CSRF base class) and its subclasses
    `ValidateCsrfToken` and `VerifyCsrfToken`. Sessions and cookies of the `web` group stay.
52. **`CMS_MCP_USER`** is the env of `mcp.user`. An email that matches no user keeps versions without a user.
53. **Only `mcp` is reserved** for the MCP server in the page route (`FilamentCms::RESERVED_PATHS`). A custom path
    (`FilamentCms::mcp('/ai/mcp')`) is not; POST never reaches the GET-only page route, so it still works, but call
    `mcp()` before `routes()`.
54. **The server** is named "Filament CMS" (version 1.0.0). Its instructions name the site (business name, else
    `app.name`) and its address. `tools/list` returns all 23 tools in one page (laravel/mcp's default is 15).

### Answers

55. **Every write answers `{ok, errors, ...}`** as JSON text and structured content. A refusal is also marked
    `isError: true`. Messages are English and not translated: the reader is the agent, not the client.
56. **Read tools answer JSON**, except `page_format` and `render_document`, which answer text.

### Documents and sections

57. **`save_document`'s `value` is optional.** Without it only the title, path, published flag or SEO change; the
    save still goes through the store with the stored value (an identical value keeps no new version). SEO is
    `seo: {title, description}` (70 and 170 characters, like the panel). Path and SEO are for pages only. A path is
    refused when any document has it (the column is unique for the whole table).
58. **`save_document` refuses kind `entry` and `kit`**, which have their own tools (`save_entry`, `save_kit`). The
    section tools take an entry whose type has a body (else they refuse it): they send only the sections, and the
    store keeps the stored `fields` (`EntryContent::merge()`). `restore_version` takes every kind; for an entry it
    brings back the version's sections and keeps the stored fields (change those with `save_entry`), and
    `list_versions` shows each entry version's `fields`. Before the review fixes the store dropped `fields`, so all
    of these refused entries.
59. **`create_document`** makes `page`, `header`, `footer` (only when there is none), `section` and `template`.
    `card` and `single` are made by the entries track per entry type. A page is unpublished unless
    `published: true`.
60. **`delete_document`** also refuses the kit and entries (`delete_entry`), and needs `confirm: true` exactly.
    Deleting a synced section answers the documents that showed it (`used_by`). Old versions are kept, as in the
    panel.
61. **`update_section`** replaces the keys sent and merges `data` key by key, so a small change needs no full
    document. **`insert_section`** keeps a sent id (refused when taken) or makes one, and answers it; an index past
    the end adds at the end. **`move_section`** clamps the index the same way. Every section tool answers the
    document's outline (id, type, layout, first words).

### Kit and settings

62. **`save_kit` takes only the parts that change** and merges them over the stored kit; a sent `custom_colors`
    replaces the whole list. A sent value the kit would drop or replace (a wrong colour, an unknown key, a custom
    colour with a fixed key) refuses the whole save. The panel's design panel cleans such values silently; for an
    agent a refusal is clearer.
63. **`save_settings` saves only the sections the builder's shop info form shows** (those with `builderFields()`:
    business, contact, hours, social) through `SiteInfo::save()`, so the rules, cleaning and picture checks are
    the same. `seo` and other panel-only sections are refused (`editable: false` in `get_settings`). Values are in
    the saved shape (`get_settings`): phones as a list of numbers; a picture as its path on the CMS disk or its
    address, and it must already be uploaded. An unknown section or field is refused.

### Rendering

64. **`render_document`: what counts as published.** A page with `is_published`, an entry with `is_published` whose
    type is registered with a path, and the header and footer (every page draws them). Anything else (an
    unpublished page or entry, a synced or saved section, a card or single design) needs `include_unpublished: true`.
    A page is drawn in the site's page view; an entry by `EntryPageRenderer`, as visitors get it (its type's single
    design with its values, then its own sections, in the page view); the header and footer as
    `SitePageRenderer::part()`. A card or single design is drawn in `<div class="sf-page">` with the values of the
    type's first entry: a single with that entry's `page.*` values, a card as the list block draws it (without
    `document.id`, no list inside). A synced or saved section is drawn the same way with the home page's `page.*`
    values. The kit is refused. The cut is at 200,000 bytes (UTF-8 safe), with an HTML comment on the first line
    saying so.

### Entries and forms

65. **Entries go through `Mcp\Contracts\McpEntries`** (`available`, `types`, `list`, `save`, `delete`). While entry
    types are registered the provider binds `Mcp\Entries\RegisteredEntries`, else `Mcp\Entries\NoEntries`, whose
    tools answer "Entries are not set up on this site". `save_entry` checks the shape (`type` and `title` for a new
    entry, `slug`, `published`, `sort`, `fields`, `sections`) and passes the MCP user. `RegisteredEntries` saves
    through `EntryStore` as a developer: `published` is `is_published`, fields are cleaned by the type, and it
    answers `{ok, errors?, id?, url?}` (`url` null for a type without a path). It refuses what the store would
    drop without a word: an unknown type, a type change, a field name the type does not have, and `sections` for a
    type without a body. `list_entries` refuses an unknown `type`. Each type also has `dynamic_values`: its
    `entry.*` keys (`EntryValues::definitions()`, the list the builder offers) with the first entry's values as
    previews, in `list_entries` and `site_overview`.
66. **Form submissions go through `Mcp\Contracts\McpFormSubmissions`** (`list`, `update`), statuses `new` `read`
    `spam`. The provider binds `Mcp\Forms\EloquentFormSubmissions` (on `Models\FormSubmission`). Meta keys with
    "ip" in their name are not returned. (Before the review fixes it had `available()` and a `NoFormSubmissions`
    for a site without the forms track; that track is merged, so they were dead code.)

### page_format

67. **Generated from the block editor**, including four private constants read by reflection (with fallbacks):
    `StyleBag::KEYS`, `PageContent::FIELDS`, and `BlockNoteRenderer::TEXT_COLORS` and `ITEM_SPACING`. Better long-term fix:
    make them public in the block editor. The maintained words are in `resources/mcp/page-format.md`; they follow
    the PHP limits where B2's builder notes differ (form `name` 100, `submit.text` 60, textarea `rows` 2 to 12).
    Its dynamic values list each entry type's `entry.*` keys, which only that type's card, entry page design and
    entry bodies can show.

## Review fixes (3 November 2026)

68. **`Documents\CmsKind`** is the one list of stored kinds and what each one is: routable, a part, one section,
    may show synced sections, belongs to an entry type, an entry design, the builder kind "Bina" opens it as, its
    own MCP tools, made by `create_document`, deleted by `delete_document`, its model class, its content handler,
    and its label (`filament-cms::documents.kinds`). Its matches have no `default`, so a new kind must be answered
    there. It is not the block editor's `Enums\DocumentKind` (what the builder edits); `builderKind()` maps one to
    the other. The stored strings did not change, and `Document::PAGE` and the others are now the enum's values.
    `Document::KINDS`, `Document::PARTS` and the other kind lists are gone. The kit document's title is the
    translated kind label ("Reka bentuk" / "Design kit") instead of "Kit".
69. **One content handler per kind** (`Documents\ContentHandler`: `PageValueContent`, `KitContent`,
    `Entries\EntryContent`) merges, checks and cleans what `DocumentStore::save()` stores, and says what a
    restored version brings back. A document of a kind this version does not know is saved as a page value.
70. **Reading sections.** `Document::contentSections()` (and `Document::sectionsOf()` for a version's content) is
    the one way to read a document's sections: upgraded and cleaned. `SitePage::$sections` still returns the stored
    sections without cleaning them, as before.
