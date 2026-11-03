<?php

namespace Vortechron\FilamentCms\Entries;

use BackedEnum;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\FilamentCms;
use Vortechron\FilamentCms\Models\Document;

/**
 * A kind of entry the developer defines, like a WordPress post type: its
 * fields, its address, and whether each entry has a body built in the page
 * builder. The client adds the entries. Register it in a service provider:
 *
 *     app(EntryTypes::class)->register(
 *         EntryType::make('product')
 *             ->label('Produk')
 *             ->pluralLabel('Produk')
 *             ->icon('heroicon-o-cube')
 *             ->fields([Field::text('price')->label('Harga'), Field::image('photo')->label('Gambar')])
 *             ->path('/produk')      // each entry at /produk/{slug}; null: no page of its own
 *             ->body(true)           // a body built in the page builder
 *             ->sort('manual'),      // newest, title or manual
 *     );
 */
final class EntryType
{
    public const SORTS = ['newest', 'title', 'manual'];

    /**
     * Field names the entry itself uses (entry.title, entry.url, its slug).
     */
    public const RESERVED_FIELDS = ['title', 'url', 'slug'];

    private string $label;

    private ?string $pluralLabel = null;

    private string|BackedEnum|null $icon = null;

    /**
     * @var list<Field>
     */
    private array $fields = [];

    private ?string $path = null;

    private bool $body = false;

    private string $sort = 'newest';

    private function __construct(public readonly string $key)
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $key) !== 1) {
            throw new InvalidArgumentException("An entry type key is a lowercase letter, then up to 39 lowercase letters, digits or dashes. Got [{$key}].");
        }

        $this->label = Str::headline($key);
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function pluralLabel(string $label): self
    {
        $this->pluralLabel = $label;

        return $this;
    }

    /**
     * A Filament icon: a Heroicon case or a name such as "heroicon-o-cube".
     */
    public function icon(string|BackedEnum|null $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * The entry's own fields, as block editor fields. Each name is a
     * lowercase letter, then lowercase letters, digits or underscores, so it
     * works as a dynamic value (`entry.{name}`). `title`, `url` and `slug`
     * are taken by the entry itself.
     *
     * @param  list<Field>  $fields
     *
     * @throws InvalidArgumentException
     */
    public function fields(array $fields): self
    {
        $names = [];

        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                throw new InvalidArgumentException('Entry type fields are '.Field::class.' objects.');
            }

            if (preg_match('/^[a-z][a-z0-9_]*$/', $field->name) !== 1 || in_array($field->name, self::RESERVED_FIELDS, true)) {
                throw new InvalidArgumentException("An entry field name is a lowercase letter, then lowercase letters, digits or underscores, and not title, url or slug. Got [{$field->name}].");
            }

            if (in_array($field->name, $names, true)) {
                throw new InvalidArgumentException("The [{$field->name}] field is given twice in the [{$this->key}] entry type.");
            }

            $names[] = $field->name;
        }

        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * Where the entries' pages live: `/produk` puts an entry at
     * `/produk/{slug}`. Null means the entries have no page of their own.
     *
     * @throws InvalidArgumentException when the address is the home page or one the site uses
     */
    public function path(?string $path): self
    {
        if ($path === null) {
            $this->path = null;

            return $this;
        }

        $clean = Document::cleanPath($path);
        $first = explode('/', $clean)[1];

        if ($clean === '/' || in_array($first, FilamentCms::RESERVED_PATHS, true)) {
            throw new InvalidArgumentException('An entry type path cannot be the home page or start with '.implode(', ', FilamentCms::RESERVED_PATHS).". Got [{$path}].");
        }

        $this->path = $clean;

        return $this;
    }

    /**
     * Whether each entry has a body built in the page builder ("Bina").
     */
    public function body(bool $body = true): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * How the screen and the list block order the entries by default:
     * newest first, by title, or in the order the client drags them.
     */
    public function sort(string $sort): self
    {
        if (! in_array($sort, self::SORTS, true)) {
            throw new InvalidArgumentException('An entry type is sorted by '.implode(', ', self::SORTS).". Got [{$sort}].");
        }

        $this->sort = $sort;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPluralLabel(): string
    {
        return $this->pluralLabel ?? $this->label;
    }

    public function getIcon(): string|BackedEnum|null
    {
        return $this->icon;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function hasBody(): bool
    {
        return $this->body;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    /**
     * The first field of a type (image, textarea...), or null.
     */
    public function firstField(string $type): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->type === $type) {
                return $field;
            }
        }

        return null;
    }
}
