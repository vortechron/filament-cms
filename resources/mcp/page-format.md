# Page format 2

Everything built in the page builder is a document. Each one has a `kind`:

| Kind | What it holds |
| --- | --- |
| `page` | A page at its own `path` (`/` is the home page). A page value. |
| `header`, `footer` | Drawn around every page. One of each. A page value. |
| `section` | A synced section: exactly one rich section, shown by pages through a `ref` section. |
| `template` | A saved section: exactly one rich section, offered as a copy in the builder. |
| `card`, `single` | How an entry type draws one entry in a list, and its own page. A page value. |
| `kit` | The design kit (see "Design kit"). Use get_kit and save_kit. |
| `entry` | One entry of an entry type. Use the entry tools. |

Every write is checked, upgraded, cleaned and saved as a version, the same as in the panel. Cleaning keeps
known keys and drops anything else, so read the document again after a save to see what was kept.

## The page value

```json
{
  "version": 2,
  "sections": [
    {
      "id": "a1b2c3d4",
      "type": "rich",
      "hidden": true,
      "lock": { "move": true, "remove": true, "content": true, "style": true },
      "data": {
        "document": [],
        "layout": "plain",
        "style": { "desktop": {}, "tablet": {}, "phone": {}, "hover": {} },
        "advanced": { "class": null, "css": null, "hide_on": [] }
      }
    },
    { "id": "k9x8", "type": "ref", "ref": 12 }
  ]
}
```

- `version` is always 2. A bare list of sections, or `{ "sections": [...] }` with no version, is format 1 and is
  upgraded with no other change.
- At most {{max_sections}} sections. A section `id` is letters, digits, `-` and `_`, at most {{max_name}} characters;
  one without an id gets a new random one. Keep ids stable: the section tools find sections by id.
- `hidden` and each `lock` key are written only when true. A hidden section stays in the builder and is left off
  the public page.
- `type` is `rich` (a section with its own words) or `ref` (a synced section).

### Rich section data

`data.document` is the section's words: a list of BlockNote blocks (see "Blocks"), at most {{max_document_kb}} KB
as JSON. The other fields are the simple look presets the client uses; a missing or wrong value becomes its default:

{{section_fields}}

`data.style` and `data.advanced` (see "Styles") are optional and win over the presets when both set the same thing.
After cleaning, empty style variants and empty advanced keys are left out.

### Layouts

`data.layout` arranges the section's blocks. The words stay the author's own; a layout only groups and places them.

{{layouts}}

### Synced sections (`ref`)

`{ "id": "k9x8", "type": "ref", "ref": 12, "hidden": true }` draws the one rich section of document 12 (kind
`section`) in its place, with the ref section's id. `ref` is a positive whole number (or an id of letters, digits,
`-` and `_`, at most {{max_name}} characters). A ref section has no `data`. Only one level: a `ref` inside a synced
section is not followed. A missing one draws nothing. Edit the synced section itself to change every page that
shows it.

### Locks

`lock` keys ({{locks}}), each `true` or left out:

- `move`: the section stays at its place.
- `remove`: it cannot be deleted or hidden.
- `content`: its `data.document` cannot change.
- `style`: its look fields, `style` and `advanced` cannot change.

Locks bind the client in the panel, not you: this server saves as a developer, so you may set, change and remove
locks. Developer-only data is also yours to change: every `lock`, every `advanced.css`, own block fields marked
`developerOnly`, and a form's `webhook` action.

## Styles

One style bag shape for sections (`data.style`) and blocks (inside their `look` prop):

```json
{ "desktop": { "padding_top": "24px", "color": "kit:text" },
  "tablet":  { "padding_top": "16px" },
  "phone":   { "padding_top": "12px", "font_size": "1rem" },
  "hover":   { "background_color": "kit:primary" } }
```

Variants: {{style_variants}}. `desktop` is the base, `tablet` applies at `max-width: 1024px`, `phone` at
`max-width: 767px`. `hover` allows only {{hover_keys}}.

{{style_keys}}

Value grammar:

- **length**: `0`, or a number with at most 3 decimals and a unit `px` `rem` `em` `%` `vw` `vh`. At most 2000 for
  px, 200 for rem and em, 100 for %, vw and vh. "length ≥ 0" refuses a minus sign.
- **color**: `#rgb`, `#rrggbb` or `#rrggbbaa` (stored lowercase), `transparent`, or `kit:<key>`, a design kit
  colour such as `kit:primary` or a custom colour key (`[a-z0-9-]{1,30}`).
- **font**: `kit:heading`, `kit:body`, or one of these fonts: {{fonts}}.
- **weight**: `300` to `900` in steps of 100.
- **line_height**: a number from 0.8 to 3, or a length.
- **radius**: a length, or `kit:radius`.
- **shadow**: {{shadows}} (the kit's shadows).
- **opacity**: a number from 0 to 1.
- Anything else is dropped, and so are unknown keys.

### Advanced

```json
{ "class": "price-card wide", "css": "selector .bn-button { letter-spacing: .1em; }", "hide_on": ["phone"] }
```

- `class`: up to {{max_classes}} class names `[A-Za-z][A-Za-z0-9_-]{0,40}`, not starting with `fbe-`.
- `hide_on`: any of {{devices}}. `desktop` means 1025px and wider.
- `css`: developer only, at most {{max_css}} characters. The word `selector` is the element itself. A value that breaks
  any rule is dropped whole: every rule's selector starts with `selector` (inside `@media` and `@supports` too, the
  only at-rules allowed); plain declarations with no rule (`color: red`) apply to the element; no `<`, no
  backslash, no `@import`, no `expression(`, `javascript:` or `behavior:`; `url()` only with a `/path` on this site
  (plain URL characters, no query string, never `//`); no `+` or `~` combinators, no `:root`, `:host` or `:has(`,
  no attribute selector on `value`, no nested rules, no `image-set()` or `src()`.

A styled section gets the class `fbe-s-{id}`; a styled block gets `fbe-b fbe-b-{id}` and keeps a stable id.

## Blocks

A section's `data.document` is a list of BlockNote blocks:

```json
{ "id": "b1", "type": "paragraph", "props": { "textAlignment": "left", "look": "" }, "content": [], "children": [] }
```

- `id`: letters, digits, `-` and `_`. A block with a `look`, and every form, gets one when it has none.
- `content`: inline content (see below), or table rows for a table, or none.
- `children`: blocks nested under it (indented items, columns, cards, toggles).
- Every block may have the prop **`look`**: a JSON **string** of `{ "style": {...}, "advanced": {...} }` (a style
  bag and advanced settings as above), or `""`. It is cleaned and stored again as compact JSON. Example:
  `"look": "{\"style\":{\"desktop\":{\"padding_top\":\"24px\"}}}"`. When the look sets `color`, `background_color`
  or `text_align`, it wins over `textColor`, `backgroundColor` and `textAlignment`.
- Common props: `textAlignment` (`left` `center` `right` `justify`), `textColor` and `backgroundColor` (named
  colours: {{named_colors}}, or `default`), `spaceTop` and `spaceBottom` (space above and below: {{item_spacing}}),
  `width` (a whole number of {{width_columns}} columns; less is narrower, up to {{max_width_columns}} stretches past
  the section padding).

Block shapes:

```json
{ "type": "heading", "props": { "level": 2, "textAlignment": "left", "look": "" }, "content": [{ "type": "text", "text": "Selamat datang", "styles": {} }], "children": [] }
{ "type": "paragraph", "props": { "look": "" }, "content": [{ "type": "text", "text": "Kami buka setiap hari.", "styles": { "bold": true } }], "children": [] }
{ "type": "bulletListItem", "props": {}, "content": [...], "children": [] }
{ "type": "image", "props": { "url": "https://example.com/a.jpg", "caption": "Kedai kami", "previewWidth": 480, "look": "" }, "children": [] }
{ "type": "button", "props": { "text": "WhatsApp kami", "url": "@site.whatsapp", "variant": "solid", "look": "" }, "children": [] }
{ "type": "ownBlock", "props": { "kind": "fbe-faq", "values": "{\"heading\":\"Soalan lazim\",\"items\":[{\"question\":\"Buka hari Ahad?\",\"answer\":\"Ya.\"}]}", "look": "" }, "children": [] }
{ "type": "form", "props": { "form": "{\"name\":\"Pertanyaan\",\"fields\":[...],\"submit\":{\"text\":\"Hantar\"},\"actions\":[]}", "look": "" }, "children": [] }
```

- **heading**: `level` 1 to 4. `isToggleable: true` makes it fold out over its children.
- **paragraph**, **quote**, **bulletListItem**, **numberedListItem**, **checkListItem** (`checked`),
  **toggleListItem** (folds out over its children), **codeBlock**, **divider**.
- **image** (`url`, `caption`, `previewWidth` in px), **video** (`url`), **audio** (`url`), **file** (`url`).
  A picture address is `https://…`, `http://…` or a `/path` on this site, or a dynamic value `@key` for image and
  video. Anything else is emptied.
- **gallery**: a photo slideshow, `images` is one address per line (`"https://a.jpg\nhttps://b.jpg"`), at most
  {{max_gallery}} photos.
- **button**: `text`, `url` and `variant` ({{button_variants}}). A link is `https://…`, `http://…`, `mailto:`,
  `tel:`, `#anchor`, a `/path` on this site, or a dynamic value `@key`. A button with another address draws as a
  plain label; one with no text draws nothing.
- **table**: `content` is `{ "type": "tableContent", "rows": [{ "cells": [[inline…], [inline…]] }] }`.
- **columnList** with **column** children (`props.width` is the column's share of the row, default 1), each
  column holding blocks in `children`.
- **card**: a fold-out card. `content` is its title, `children` its body, props `url` (a picture), `buttonColor`
  (`#rrggbb`), `open`.
- **spacer**: `size` is `sm` `md` `lg` or `xl`.
- **ownBlock**: a block the site defines and draws (site_overview lists them with their fields). `kind` is the
  block kind; **`values` is a JSON string** of the block's field values. Values are cleaned by the block's
  fields: unknown keys are dropped, a missing or wrong value becomes the field's default. Field types:
  {{field_types}}. A `link` field takes a link or `@key`, an `image` field a picture address or `@key`, a `list`
  field a list of objects with its own fields (cut to `maxItems`). Blocks on this site: {{own_blocks}}.
- **form**: a form visitors send (see "Forms"). **`form` is a JSON string.**

### Inline content

```json
[
  { "type": "text", "text": "Hubungi ", "styles": { "bold": true, "italic": false } },
  { "type": "link", "href": "@site.whatsapp", "content": [{ "type": "text", "text": "WhatsApp", "styles": {} }] },
  { "type": "dynamic", "props": { "key": "site.phone" } }
]
```

- Text `styles`: `bold`, `italic`, `underline`, `strike`, `code` (true or false), `textColor` and
  `backgroundColor` (named colours as above).
- A link's `href` follows the button link rules. A link with another address is unwrapped into its words.
- A **dynamic** item draws a value from the site, the page or an entry when the page is drawn. An empty value
  draws nothing.

## Dynamic values

A key is a lowercase name and one to three dotted parts: `{{dynamic_key}}`.

- In text: the inline item `{ "type": "dynamic", "props": { "key": "site.phone" } }`.
- In links (button `url`, inline link `href`, own block `link` fields): `@site.whatsapp`, resolved and then checked
  as a link when the page is drawn.
- In pictures (image and video `url`, own block `image` fields): `@site.logo`, resolved and then checked as a
  picture address.
- In own block `text` and `textarea` fields: short codes `{key}` inside the text, such as
  `Hubungi {site.name} di {site.phone}`. A link or image field takes a whole `@key`, never `{key}`.

Find the keys with `site_overview` (`dynamic_values`, with their current values) or the list below. A site adds its
own through its settings sections; `get_settings` lists those sections.

Values on this site:

{{dynamic_values}}

## Forms

The `form` prop of a form block is a JSON string of:

```json
{
  "name": "Borang pertanyaan",
  "fields": [
    { "id": "f1", "type": "text", "name": "nama", "label": "Nama", "placeholder": "", "required": true, "width": "half" },
    { "id": "f2", "type": "tel", "name": "telefon", "label": "Telefon", "required": true, "width": "half" },
    { "id": "f3", "type": "select", "name": "servis", "label": "Servis", "options": ["Fotostat", "Parcel"] },
    { "id": "f4", "type": "textarea", "name": "mesej", "label": "Mesej", "rows": 4 },
    { "id": "f5", "type": "consent", "name": "setuju", "label": "Saya setuju dihubungi.", "required": true }
  ],
  "submit": { "text": "Hantar", "align": "left" },
  "success": "Terima kasih. Kami akan hubungi anda.",
  "actions": [
    { "type": "email", "to": "@site.email", "subject": "Pertanyaan baru dari website" },
    { "type": "whatsapp", "to": "@site.whatsapp_number", "message": "Hai, saya {nama}. {mesej}" },
    { "type": "redirect", "url": "/terima-kasih" },
    { "type": "webhook", "url": "https://hooks.example.com/x" }
  ]
}
```

- Field types: {{form_field_types}}. At most {{max_fields}} fields. `name` is `[a-z][a-z0-9_]{0,39}`, unique in the
  form (a missing or taken name is made from the label, then `_2`, `_3`). `width` is `full` or `half`. `options`
  (up to {{max_options}} strings) only for {{choice_types}}; `rows` (2 to 12) only for `textarea`. Labels and
  placeholders up to {{max_label}} characters.
- `name` up to 100 characters, `submit.text` up to 60, `success` up to 500. `submit.align`: {{form_aligns}}. An empty
  button text or success message uses the page language's own words.
- Actions, at most one of each ({{form_actions}}): `email` (`to` is an email address or `@site.email`, `subject`
  up to 200); `whatsapp` (`to` is `@site.whatsapp_number`, another `@key`, or a phone of 8 to 15 digits; `message`
  up to 1000, `{name}` placeholders are filled from the fields); `redirect` (`url` follows the button link
  rules); `webhook` (developer only, `https://` only). An action with a bad or empty address is dropped.
- Every submission is saved (list_form_submissions). The site sends the actions from the stored form, never from
  the visitor.

## Design kit

The site's design values, one document of kind `kit`:

```json
{{kit_defaults}}
```

- `colors`: the fixed keys {{kit_colors}}, each `#rgb`, `#rrggbb` or `#rrggbbaa`.
- `custom_colors`: up to {{max_custom_colors}} of `{ "key": "gold", "label": "Emas", "value": "#c59d6e" }`; keys
  `[a-z0-9-]{1,30}` that are not fixed keys. Use them in styles as `kit:<key>`.
- `fonts.heading`, `fonts.body`: `theme` or one of the fonts above.
- `text.base_size`, `radius`, `buttons.radius`, `forms.radius`: a length in px, rem or em. `text.heading_weight`,
  `buttons.font_weight`: `300` to `900`.
- `buttons.style`: {{button_styles}}. `buttons.size`: {{button_sizes}}. `buttons.text_transform`:
  {{text_transforms}}. `forms.style`: {{form_styles}}. `forms.labels`: {{form_labels}}.
- A wrong value is refused by save_kit. Every section follows the kit through `kit:<key>` colours and the
  `--kit-*` CSS variables.
