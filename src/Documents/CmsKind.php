<?php

namespace Vortechron\FilamentCms\Documents;

use Closure;
use Vortechron\FilamentBlockEditor\Enums\DocumentKind;

/**
 * The kinds of document the CMS stores (`cms_documents.kind`), and what
 * each one is. Adding a kind means adding a case here and answering each
 * question below; every list and check in the CMS asks this enum.
 *
 * Not the block editor's Enums\DocumentKind, which is what the page
 * builder edits: builderKind() maps one to the other.
 */
enum CmsKind: string
{
    case Page = 'page';
    case Header = 'header';
    case Footer = 'footer';

    /**
     * A synced section: one rich section many pages show (`type: ref`).
     */
    case Section = 'section';

    /**
     * A saved section: a copy the builder's add panel offers as a template.
     */
    case Template = 'template';

    /**
     * The design kit.
     */
    case Kit = 'kit';

    /**
     * How an entry type draws one entry in a list.
     */
    case Card = 'card';

    /**
     * The top of each entry's own page.
     */
    case Single = 'single';

    case Entry = 'entry';

    /**
     * The stored values of the kinds that pass `$where` (all without one).
     *
     * @param  (Closure(self): bool)|null  $where
     * @return list<string>
     */
    public static function values(?Closure $where = null): array
    {
        return array_values(array_map(
            fn (self $kind): string => $kind->value,
            array_filter(self::cases(), $where ?? fn (): bool => true),
        ));
    }

    /**
     * The header and footer, in that order.
     *
     * @return list<self>
     */
    public static function parts(): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind): bool => $kind->isPart()));
    }

    /**
     * The stored kind, or null for one this version does not know.
     * Unsaved documents without a kind are pages (the column's default).
     */
    public static function of(?string $kind): ?self
    {
        return self::tryFrom($kind ?? self::Page->value);
    }

    /**
     * Visitors open it at its own address (`path`).
     */
    public function isRoutable(): bool
    {
        return match ($this) {
            self::Page, self::Entry => true,
            self::Header, self::Footer, self::Section, self::Template, self::Kit, self::Card, self::Single => false,
        };
    }

    /**
     * Drawn around every page; the site has one of each.
     */
    public function isPart(): bool
    {
        return match ($this) {
            self::Header, self::Footer => true,
            self::Page, self::Section, self::Template, self::Kit, self::Card, self::Single, self::Entry => false,
        };
    }

    /**
     * Its content is a page value with sections (the kit's is not).
     */
    public function holdsSections(): bool
    {
        return $this !== self::Kit;
    }

    /**
     * It holds exactly one rich section, or none yet.
     */
    public function holdsOneSection(): bool
    {
        return match ($this) {
            self::Section, self::Template => true,
            self::Page, self::Header, self::Footer, self::Kit, self::Card, self::Single, self::Entry => false,
        };
    }

    /**
     * Its sections may show a synced section through a `ref`.
     */
    public function mayShowSynced(): bool
    {
        return $this->holdsSections() && ! $this->holdsOneSection();
    }

    /**
     * It belongs to an entry type (`entry_type`): an entry, or its type's card or single design.
     */
    public function belongsToEntryType(): bool
    {
        return match ($this) {
            self::Entry, self::Card, self::Single => true,
            self::Page, self::Header, self::Footer, self::Section, self::Template, self::Kit => false,
        };
    }

    /**
     * One of an entry type's two designs, made on first use.
     */
    public function isEntryDesign(): bool
    {
        return match ($this) {
            self::Card, self::Single => true,
            self::Page, self::Header, self::Footer, self::Section, self::Template, self::Kit, self::Entry => false,
        };
    }

    /**
     * What the page builder edits it as, or null when "Bina" does not open
     * it. An entry's body is built as a page, a saved section as one section.
     */
    public function builderKind(): ?DocumentKind
    {
        return match ($this) {
            self::Page, self::Entry => DocumentKind::Page,
            self::Header => DocumentKind::Header,
            self::Footer => DocumentKind::Footer,
            self::Section, self::Template => DocumentKind::Section,
            self::Card => DocumentKind::Card,
            self::Single => DocumentKind::Single,
            self::Kit => null,
        };
    }

    /**
     * The MCP tools of its own, when the document tools do not change it.
     */
    public function mcpTools(): ?string
    {
        return match ($this) {
            self::Kit => 'get_kit and save_kit',
            self::Entry => 'list_entries, save_entry and delete_entry',
            self::Page, self::Header, self::Footer, self::Section, self::Template, self::Card, self::Single => null,
        };
    }

    /**
     * create_document makes it. Entry designs are made by the entry type's
     * screen, entries by save_entry, the kit by save_kit.
     */
    public function mcpCreatable(): bool
    {
        return match ($this) {
            self::Page, self::Header, self::Footer, self::Section, self::Template => true,
            self::Kit, self::Card, self::Single, self::Entry => false,
        };
    }

    /**
     * delete_document deletes it (never the home page, see DeleteDocument).
     * The header, the footer and the kit are kept; entries have delete_entry.
     */
    public function mcpDeletable(): bool
    {
        return match ($this) {
            self::Page, self::Section, self::Template, self::Card, self::Single => true,
            self::Header, self::Footer, self::Kit, self::Entry => false,
        };
    }

    public function label(): string
    {
        return __("filament-cms::documents.kinds.{$this->value}");
    }
}
