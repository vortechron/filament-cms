# Page format 2: deviations of track C1 (CMS core)

The contract is `docs/v2-plan.md` in `vortechron/filament-block-editor`. This file lists where track C1
chose differently or added detail, and why. 3 November 2026.

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
   plain error under `sections`.
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
    resolvers return the context value, so the builder's preview of them is null.
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

# C2 (CMS forms), 3 November 2026

## Where a form posts and how it is found

1. **`document.id` in the RenderContext values** (`CmsFormEndpoint::VALUE`). `SitePageRenderer::pageValues()` adds it
    for a saved page, and the header and footer get the same values, so their forms post to the page they are on.
    **Track C3:** put `document.id` (the entry's id) in the values when drawing an entry page. `hiddenFields()` adds
    nothing. Without the value, or without `FilamentCms::routes()`, the form has no endpoint.
2. **The route takes any id** and the controller answers 404 for one that is not a number. With `whereNumber()`
    the GET catch-all answered such a POST with 405.
3. **Where the stored form is looked for**, in this order: the document's own sections, an entry's type `single`
    document (C3 draws it first), the header, the footer, then the synced sections any of them shows (by the
    `ref` section's id, one level). Only sections visitors see count (hidden ones are skipped). A key with no
    section id (`{blockId}`) is looked for in all of them. A key over 100 characters or a form that is not there
    is a 404. Not found: a form inside an entry card drawn by a list block.

## Checks

4. **Quiet refusals go to the page's `#form-{blockId}`**, not to the Referer, with no flash and no error.
5. **The rate limit counts every send that passes the bot checks, good or not**, keyed by the IP and the block id,
    so a header form has one limit on every page. Its "Cuba lagi sebentar" shows under the form's first field
    (`form.{blockId}.{firstField}`): the form block only shows errors by field.
6. **Validation messages are the package's own** (`filament-cms::forms.validation`, Malay and English), with the
    field labels as names, so a Malay site needs no `lang/ms/validation.php`.

## What is saved

7. `data` has one value per stored field, in the form's order: text as a string or null, checkboxes as a list,
    consent as true or false. `form_name` is null when the form has no name. `meta` is `page` (the document path),
    `user_agent` (cut to 300 characters) and `ip_hash` (HMAC-SHA256 of the IP with the app key).

## Actions

8. **Email:** skipped (with a log line) when the address is empty or not an email, or `mail.default` is `log` or
    `array`. Reply-To is the first email field the visitor filled. An empty subject is "Borang baru: {name}". HTML
    only, every value escaped.
9. **Webhook** (security review, 3 November 2026): JSON `{id, form, page, data, sent_at}`. The host must be a plain
    ASCII name (no trailing dot, the last part starting with a letter, so `2130706433` and `127.1` are refused) or
    an IP address written in full. Every address must be public: also refused are carrier-grade NAT (`100.64.0.0/10`,
    which Tailscale uses), multicast, and the IPv6 forms of private IPv4 addresses (mapped, NAT64, 6to4). curl
    connects to the checked address for any host and port (`CURLOPT_CONNECT_TO`), through Guzzle's `CurlHandler`,
    with `proxy` `''` (no proxy from the environment) and no redirects. Every address curl reports
    (`CURLINFO_PRIMARY_IP`, read in `on_stats`) must be the checked one, or the send failed. Without the curl
    extension the webhook is refused. Logs name the host only, never the link.
10. **WhatsApp:** the number goes through `cms_whatsapp_number()` (a leading 0 becomes 60) and must be 8 to 15
    digits, else the action is skipped. A `{name}` the form has no field for stays as written. Lists are joined
    with ", ", ticks are "Ya" or "Tidak". An empty message lists every field as "Label: value". The message is cut
    to 2000 characters. WhatsApp wins over redirect.
11. **Redirect:** only a `/path` on this site or an `http(s)://` link. A `mailto:`, `tel:` or `#` link is no place to
    send a visitor, so they go back to the form.
12. **The success flash is set for WhatsApp and redirect too**, so a visitor who comes back sees the message.

## The Borang screen

13. Top level in the menu (no group), above Halaman. "Mark as new" is labelled "Tanda belum dibaca" (Mark as
    unread). Labels are found in hidden sections too, so an older submission keeps them. The CSV is our own
    streamed file, not Filament's exporter (that one needs a queue and its own tables): UTF-8 with a byte order
    mark, one column per field name seen in the filtered rows, and a `'` before a value a spreadsheet would run as
    a formula.

## C3: entries

Track C3 (entries), 3 November 2026.

### Shapes and names

20. **Classes.** `Entries\EntryType` (the definition) and `Entries\EntryTypes` (the singleton registry, with
    `register`, `forget`, `has`, `get`, `all`). `Models\Entry` is kind `entry` (a global scope, like `SitePage`), so a
    host writes an `EntryPolicy` for it. `Entries\EntryStore::save(Entry, $data, $user, ?$developer)` saves the
    title, slug, publish switch and content in one save; the content part goes through `DocumentStore`.
21. **Field names** must match `[a-z][a-z0-9_]*` (stricter than `Field`), so each one is a valid dynamic value key
    `entry.{name}`. A name given twice is refused. `RESERVED_PATHS` for a type path copies the prefixes in
    `FilamentCms::routes()`; keep the two in step. Two types may not share a path.
22. **`DocumentStore` for kind `entry`** takes `fields` and/or `sections` (a bare list counts as sections). The part
    not sent keeps its stored value, so the page builder (sections only) and the entry form (fields only) never
    erase each other. An unregistered `entry_type` is refused (`entry_type`), `fields` that is not an array is refused
    (`fields`). Sections sent for a type without a body are dropped, not refused.
23. **Slug.** No slug column: the slug is the last part of `path`. A new entry's slug comes from its title (the
    type key when the title has no letters), at most 80 characters. It stays when the title changes; a given slug
    replaces it, an empty one makes it from the title again. "Unique within the type" became **unique among all
    documents**, because pages and entries share the unique `path` column. For the same reason the Halaman form now
    refuses a page address an entry already has (it used to check pages only, and would have hit the index).
24. **A type path changed in code** moves each entry when it is next saved. Until then the old address keeps
    working. A type changed to `path(null)` (or removed) shows no entry pages at all.
25. **Image fields** store a public address. The form's upload field works with paths on the CMS disk; a submitted
    path is kept only when it is a file of CmsImage's types directly inside `cms/` (the same rule as the logo, C1
    deviation 14). A stored picture from elsewhere (an MCP save of an `https://` address) is kept when the form sends
    none, because the upload field can not show it; inside a list it is dropped on the next form save.

### Screens

26. **One resource, one page class**: `EntryResource` with the page `ManageEntries` at `/admin/entries/{type}`.
    The resource returns one navigation item per type (`getNavigationItems()`), sort -8 in "Kandungan". Create and
    edit open in a modal, as on the Seksyen screen. Publish and unpublish are row actions that save through
    `EntryStore`; the list shows "Diterbitkan" as an icon.
27. **"Reka kad" and "Reka halaman entri"** are actions that make the design on first use (a Livewire call, not a
    GET) and redirect to `BuildSitePage`. "Reka halaman entri" shows only for a type with a path. Both need the
    Halaman screen on and follow the SitePage rule (C1 deviation 17). An entry's body follows the Entry policy
    (`EntryResource::canEdit($entry)`).
28. **The builder for an entry** opens only for a registered type with `body(true)` while entries are on, else 404.
    Its `kind` is `page`, `viewUrl` is the entry's page when published, and every entry, card and single goes back
    to the type's screen. With `entries(false)` the builder does not offer the `cms-entries` block.

### Dynamic values and drawing

29. **`entry.*` values**: text, textarea and number fields are text; a select is its option's **label**; link and
    image fields are links and pictures (a stored `@key` is followed one level, then checked). Toggles and lists are
    not values. `entry.url` is null for a type without a path. Labels: "Tajuk" / "Alamat entri", then each field's
    label; the group is the type's label.
30. **Per-builder values (block editor gap).** The block editor's trait builds `dynamicValues` from the registry
    only, with an empty `RenderContext` for previews; it has no hook for a builder's own values. `BuildSitePage`
    already overrides `getPageBuilderOptions()`, so it appends the type's `entry.*` definitions there. **Block editor
    could add:** a `getPageBuilderDynamicValues(): array` hook merged after the registry's, and a
    `getPageBuilderRenderValues(): array` passed as the `RenderContext` values of `definitions()`, so `page.*`
    previews work too.
31. **Previews.** A card or single is drawn with the type's first entry (published ones first, in the type's
    order). An entry body's preview is its real page: the single design as visitors see it, inside
    `<div data-part="page" class="sf-page">`, then the editable body.
32. **Sections that are not the edited value carry `data-entry-block-id`** in the builder (cards in a list block,
    the single design above a body), not `data-block-id`, because the builder's canvas, drag and drop and swaps find
    sections by `data-block-id` anywhere in the frame (`Entries\Inert`). Visitors get the normal wrappers.
33. **The list block** is registered (and re-registered with the new type options) by `EntryTypes::register()` and
    forgotten when the last type goes, so it works whatever order the providers boot in. Its labels are translated
    then. `order` defaults to `newest`, `columns` to `3`, `limit` to 6. A stored type that is no longer registered
    falls back to the first type (a select field's rule). Cards are drawn as visitors see them. A list inside a card,
    also in the card's builder preview, draws nothing (a note in the builder). A designed card's style rules are
    added once per card drawn (the style sheet does not merge repeats).
34. **The plain card and page** use the design kit's variables in `resources/css/entries.css`, printed inline by
    `<x-filament-cms::entries-styles />`, instead of borrowing core block classes from the block editor's page CSS.
35. **Entry pages.** `ShowSitePage` looks for a published page first, then a published entry of a registered type
    with a path. The page view gets `$page` (the entry, with `seo_title` = its title and `seo_description` = its first
    textarea, at most 160 characters, set on a copy, not saved), `$sections` (its body), `$content` (design or plain
    layout, then the body), `$header`, `$footer`. `SitePageRenderer` gained `layout()` for this, and
    `preview(..., $values)` for the card and single previews. With `entries(false)` entry pages are still served
    (drawing has no panel to ask).
