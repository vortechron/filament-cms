<?php

namespace Vortechron\FilamentCms\Entries;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Blocks\OwnBlock;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentBlockEditor\Rendering\Pager;
use Vortechron\FilamentBlockEditor\Rendering\PageRenderer;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Forms\CmsFormEndpoint;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;

/**
 * The "cms-entries" own block: a grid of the published entries of one type.
 * Each entry is drawn with the type's card design and that entry's values,
 * or with the built-in card (`filament-cms::entries.card`: first picture,
 * title, first textarea, a link to its page) when no card is designed.
 *
 * It may show only the entries linked to one entry ("Only linked to", like
 * the items of one category), and with page buttons the limit is per page:
 * the visitor flips through up to MAX entries with ‹ › (Rendering\Pager).
 *
 * A card never draws a list: a cms-entries block inside a card draws
 * nothing (a note in the builder), so a list can not draw itself forever.
 */
final class EntryList
{
    public const KIND = 'cms-entries';

    public const MAX = 48;

    /**
     * How many cards are being drawn right now, anywhere.
     */
    private static int $cards = 0;

    public function __construct(private readonly PageRenderer $pages) {}

    /**
     * Adds the block with the current types to the builder's blocks, or
     * takes it away when there are none.
     */
    public static function register(BlockRegistry $blocks, EntryTypes $types): void
    {
        if ($types->all() === []) {
            $blocks->forget(self::KIND);

            return;
        }

        $options = array_map(fn (EntryType $type): string => $type->getPluralLabel(), $types->all());

        $blocks->register(OwnBlock::make(self::KIND)
            ->label(__('filament-cms::entries.list'))
            ->icon('layout-grid')
            ->description(__('filament-cms::entries.list_help'))
            ->fields([
                Field::select('type')->label(__('filament-cms::entries.list_type'))->options($options),
                Field::select('linked')->label(__('filament-cms::entries.list_linked'))->options(fn (): array => self::linkChoices())->default(''),
                Field::number('limit')->label(__('filament-cms::entries.list_limit'))->min(1)->max(self::MAX)->default(6),
                Field::toggle('pages')->label(__('filament-cms::entries.list_pages'))->default(false),
                Field::select('columns')->label(__('filament-cms::entries.list_columns'))->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4'])->default('3'),
                Field::select('order')->label(__('filament-cms::entries.list_order'))->options([
                    'newest' => __('filament-cms::entries.order_newest'),
                    'title' => __('filament-cms::entries.order_title'),
                    'manual' => __('filament-cms::entries.order_manual'),
                ]),
                Field::text('empty_text')->label(__('filament-cms::entries.list_empty_text'))->default(__('filament-cms::entries.list_empty', [], ContentLocale::site())),
            ])
            ->view('filament-cms::entries.block'));
    }

    /**
     * Runs a draw of a card: lists inside it draw nothing.
     *
     * @param  Closure(): string  $draw
     */
    public static function drawingCard(Closure $draw): string
    {
        self::$cards++;

        try {
            return $draw();
        } finally {
            self::$cards--;
        }
    }

    /**
     * The block's HTML.
     *
     * @param  array<string, mixed>  $values  the block's cleaned values
     * @param  array<mixed>  $block  the BlockNote block, for its id
     */
    public function render(array $values, RenderContext $context, array $block = []): string
    {
        $type = app(EntryTypes::class)->get(is_string($values['type'] ?? null) ? $values['type'] : null);

        if (self::$cards > 0 || $type === null) {
            return $context->forBuilder ? view('filament-cms::entries.note', ['text' => __('filament-cms::entries.'.($type === null ? 'list_no_type' : 'list_in_card'))])->render() : '';
        }

        $perPage = max(1, min(self::MAX, (int) ($values['limit'] ?? 6)));
        $paged = ($values['pages'] ?? false) === true;
        $link = self::linkFilter($type, $values['linked'] ?? null);

        $entries = Entry::query()
            ->published()
            ->ofType($type->key)
            ->when($link !== null, fn ($query) => $query->where('content->fields->'.$link['field'], $link['id']))
            ->ordered(is_string($values['order'] ?? null) ? $values['order'] : $type->getSort())
            ->limit($paged ? self::MAX : $perPage)
            ->get();

        if ($entries->isEmpty()) {
            $text = trim((string) ($values['empty_text'] ?? ''));

            return $text === '' ? '' : view('filament-cms::entries.empty', ['text' => $text])->render();
        }

        $card = PageContent::visible(EntryTemplates::find($type, Document::CARD)?->contentSections() ?? []);

        $cards = $entries->map(fn (Entry $entry): string => $card === []
            ? view('filament-cms::entries.card', ['entry' => $entry, 'type' => $type, 'values' => EntryValues::of($entry)])->render()
            : $this->designed($card, $entry, $context))->all();

        $pages = array_map(fn (array $page): string => view('filament-cms::entries.list', [
            'columns' => max(1, min(4, (int) ($values['columns'] ?? 3))),
            'type' => $type->key,
            'cards' => $page,
        ])->render(), Pager::pages($cards, $perPage, $paged));

        return view('filament-block-editor::pager', ['name' => Pager::name($block), 'pages' => $pages])->render();
    }

    /**
     * The "Only linked to" choices: every entry a link field of a type
     * made in the panel can point at, as `type.field:id`, after "any".
     *
     * @return list<array{value: string, label: string}>
     */
    public static function linkChoices(): array
    {
        $choices = [['value' => '', 'label' => __('filament-cms::entries.list_linked_any')]];

        try {
            foreach (StoredEntryTypes::definitions() as $definition) {
                foreach ($definition['fields'] ?? [] as $field) {
                    if (($field['type'] ?? null) !== 'entry') {
                        continue;
                    }

                    $targets = Entry::query()->ofType((string) $field['target'])->orderBy('title')->orderBy('id')->get(['id', 'title']);

                    foreach ($targets as $target) {
                        $choices[] = [
                            'value' => "{$definition['key']}.{$field['name']}:{$target->getKey()}",
                            'label' => ($definition['plural_label'] ?? $definition['label'] ?? $definition['key']).' · '.$field['label'].': '.$target->title,
                        ];
                    }
                }
            }
        } catch (QueryException) {
            // Before the migrations: only "any".
        }

        return $choices;
    }

    /**
     * The link field and entry id a list shows only the entries of, when
     * `type.field:id` names a link field of this type.
     *
     * @return array{field: string, id: string}|null
     */
    private static function linkFilter(EntryType $type, mixed $linked): ?array
    {
        if (! is_string($linked) || preg_match('/^([a-z0-9-]+)\.([A-Za-z0-9_-]+):(\d+)$/', $linked, $match) !== 1 || $match[1] !== $type->key) {
            return null;
        }

        $definition = collect(StoredEntryTypes::definitions())->firstWhere('key', $type->key);

        foreach ($definition['fields'] ?? [] as $field) {
            if (($field['name'] ?? null) === $match[2] && ($field['type'] ?? null) === 'entry') {
                return ['field' => $match[2], 'id' => $match[3]];
            }
        }

        return null;
    }

    /**
     * The values a card is drawn with: the page's and the entry's, without
     * `document.id`. A form in a card is never looked for when it is sent
     * (Forms\FormLocator), so it is drawn not connected (its button
     * disabled) instead of posting into a 404.
     *
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function cardValues(array $page, array $entry): array
    {
        return [...Arr::except($page, CmsFormEndpoint::VALUE), ...$entry, CmsFormEndpoint::VALUE => null];
    }

    /**
     * One entry drawn with the card design, as visitors see it.
     *
     * @param  list<array<string, mixed>>  $card
     */
    private function designed(array $card, Entry $entry, RenderContext $context): string
    {
        $html = self::drawingCard(fn (): string => $this->pages->render($card, new RenderContext(values: self::cardValues($context->values, EntryValues::of($entry)))));

        return $context->forBuilder ? Inert::html($html) : $html;
    }
}
