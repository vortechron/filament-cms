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
