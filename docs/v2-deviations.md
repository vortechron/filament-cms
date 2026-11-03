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

## C4 — MCP (3 November 2026)

The site's MCP server (`FilamentCms::mcp()`, `Mcp\CmsServer`) on `laravel/mcp` ^1.1 (1.1.2 installed).

### Route and access

20. **Middleware order:** the rate limit (`ThrottleRequests::with(60, 1, 'filament-cms-mcp')`, by IP), then
    `CmsMcpToken`, then laravel/mcp's own. The limit counts before the token check, so token guessing is limited
    too. The GET and DELETE answers (405) sit behind the token as well, so without a token nothing shows the
    address exists.
21. **CSRF:** the route leaves out `PreventRequestForgery` (Laravel 13's CSRF base class) and its subclasses
    `ValidateCsrfToken` and `VerifyCsrfToken`. Sessions and cookies of the `web` group stay.
22. **`CMS_MCP_USER`** is the env of `mcp.user`. An email that matches no user keeps versions without a user.
23. **Only `mcp` is reserved** in the page route. A custom path (`FilamentCms::mcp('/ai/mcp')`) is not; POST never
    reaches the GET-only page route, so it still works, but call `mcp()` before `routes()`.
24. **The server** is named "Filament CMS" (version 1.0.0). Its instructions name the site (business name, else
    `app.name`) and its address. `tools/list` returns all 23 tools in one page (laravel/mcp's default is 15).

### Answers

25. **Every write answers `{ok, errors, ...}`** as JSON text and structured content. A refusal is also marked
    `isError: true`. Messages are English and not translated: the reader is the agent, not the client.
26. **Read tools answer JSON**, except `page_format` and `render_document`, which answer text.

### Documents and sections

27. **`save_document`'s `value` is optional.** Without it only the title, path, published flag or SEO change; the
    save still goes through the store with the stored value (an identical value keeps no new version). SEO is
    `seo: {title, description}` (70 and 170 characters, like the panel). Path and SEO are for pages only. A path is
    refused when any document has it (the column is unique for the whole table).
28. **`save_document`, the section tools and `restore_version` refuse kind `entry`** (and `kit`, which has its
    own tools): `DocumentStore` keeps only `version` and `sections`, so it would drop an entry's `fields`.
29. **`create_document`** makes `page`, `header`, `footer` (only when there is none), `section` and `template`.
    `card` and `single` are made by the entries track per entry type. A page is unpublished unless
    `published: true`.
30. **`delete_document`** also refuses the kit and entries (`delete_entry`), and needs `confirm: true` exactly.
    Deleting a synced section answers the documents that showed it (`used_by`). Old versions are kept, as in the
    panel.
31. **`update_section`** replaces the keys sent and merges `data` key by key, so a small change needs no full
    document. **`insert_section`** keeps a sent id (refused when taken) or makes one, and answers it; an index past
    the end adds at the end. **`move_section`** clamps the index the same way. Every section tool answers the
    document's outline (id, type, layout, first words).

### Kit and settings

32. **`save_kit` takes only the parts that change** and merges them over the stored kit; a sent `custom_colors`
    replaces the whole list. A sent value the kit would drop or replace (a wrong colour, an unknown key, a custom
    colour with a fixed key) refuses the whole save. The panel's design panel cleans such values silently; for an
    agent a refusal is clearer.
33. **`save_settings` saves only the sections the builder's shop info form shows** (those with `builderFields()`:
    business, contact, hours, social) through `SiteInfo::save()`, so the rules, cleaning and picture checks are
    the same. `seo` and other panel-only sections are refused (`editable: false` in `get_settings`). Values are in
    the saved shape (`get_settings`): phones as a list of numbers; a picture as its path on the CMS disk or its
    address, and it must already be uploaded. An unknown section or field is refused.

### Rendering

34. **`render_document`: what counts as published.** A page or entry with `is_published`, and the header and
    footer (every page draws them). Anything else (an unpublished page, a synced or saved section, a card or
    single design, the parts of an entry) needs `include_unpublished: true`. A page is drawn in the site's page
    view; the header and footer as `SitePageRenderer::part()`; other kinds as their sections in
    `<div class="sf-page">` with the home page's `page.*` values. The kit is refused. The cut is at 200,000 bytes
    (UTF-8 safe), with an HTML comment on the first line saying so.

### Entries and forms

35. **Entries go through `Mcp\Contracts\McpEntries`** (`available`, `types`, `list`, `save`, `delete`). The CMS
    binds `Mcp\Entries\NoEntries`, whose tools answer "Entries are not set up on this site". `save_entry` checks
    the shape (`type` and `title` for a new entry, `slug`, `published`, `sort`, `fields`, `sections`) and passes
    the MCP user.
36. **Form submissions go through `Mcp\Contracts\McpFormSubmissions`** (`available`, `list`, `update`), statuses
    `new` `read` `spam`. The provider binds `Mcp\Forms\EloquentFormSubmissions` on `Models\FormSubmission` when
    that class exists, else `NoFormSubmissions`. Meta keys with "ip" in their name are not returned.

### page_format

37. **Generated from the block editor**, including four private constants read by reflection (with fallbacks):
    `StyleBag::KEYS`, `PageContent::FIELDS`, and `BlockNoteRenderer::TEXT_COLORS` and `ITEM_SPACING`. Better long-term fix:
    make them public in the block editor. The maintained words are in `resources/mcp/page-format.md`; they follow
    the PHP limits where B2's builder notes differ (form `name` 100, `submit.text` 60, textarea `rows` 2 to 12).
