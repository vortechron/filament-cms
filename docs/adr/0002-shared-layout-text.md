# Shared layout with optional language text

Language versions share one layout and default text. Linked rows retain their IDs,
paths, titles, SEO, publication flags and independent text history. Moving their
visitor words to `text_overrides` avoids making owners repeat layout changes while
keeping old form endpoints and links valid. The root stores `shared_format = 1`
and a monotonically increasing `edit_version`; every converted content, metadata
or restore write checks that version while holding the root lock.

This replaces ADR 0001 only for newly created and explicitly converted groups.
The additive schema migration never rewrites content. Conversion checks fresh
pairs and refuses unsupported content or differences in shared layout, media or actions. Registered
missing defaults and equivalent internal links are canonicalized; no conflicting
layout is chosen automatically. Actual translated text, including equal values and
intentional blanks, is retained. Existing revision payloads are never rewritten.
A legacy translated restore changes text only, and refuses an incompatible layout.

All rendering and form lookup resolve through `DocumentTranslations`. The editor
gets the canonical layout with optional locale maps; the database keeps those maps
on linked rows. Entry fields and settings keep their existing shared-fact models,
with explicit text allowlists. Entry bodies keep the editor's optional locale maps.
The design costs extra resolution and version checks, but prevents independent
language layouts and stale editors from overwriting each other.

## Rich text

Each language owns the complete inline value of a rich-text field, including bold,
italic and inline links. Page layout, theme colours, block styling, media, button
destinations and form actions stay shared. A Malay sentence and its English
translation can put emphasis and links on different words; sharing their text-run
positions cannot express that safely.

A rich field keeps its identity when its words are empty or its text runs merge or
split. Missing language text falls back; an explicit empty value stays empty.
Changing Malay words or formatting must never discard the saved English value.
The editor package owns rich-value validation and resolution; CMS callers keep
using its opaque text maps. This costs one inline value per translated field,
without copying the page layout.
