<?php

namespace Vortechron\FilamentCms\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsPage;
use Vortechron\FilamentCms\ContentForm;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\CmsRevision;

/**
 * The client edits one page here: /admin/content?key=home. Each registered
 * page gets its own menu item under "Kandungan".
 *
 * @property-read Schema $form
 */
class EditContent extends Page
{
    protected static ?string $slug = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    #[Locked]
    public string $key = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->key = $this->keyFromRequest();

        abort_unless($this->canEdit($this->key), 404);

        $this->form->fill(ContentForm::fill($this->definition(), $this->cms()->saved($this->key)));
    }

    public function getTitle(): string
    {
        return $this->definition()::label();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && CmsAccess::allows('pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-cms::cms.content');
    }

    /**
     * One menu item for every page in config/filament-cms.php.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = [];
        $sort = 0;

        foreach (static::editablePages() as $key => $class) {
            $items[] = NavigationItem::make($class::label())
                ->group(static::getNavigationGroup())
                ->icon(static::$navigationIcon)
                ->sort($sort++)
                ->url(static::getUrl(['key' => $key]))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteName())
                    && request()->query('key', array_key_first(static::editablePages())) === $key);
        }

        return $items;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(ContentForm::components($this->definition()))
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('filament-cms::cms.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        app(ContentStore::class)->save($this->key, $this->form->getState(), Filament::auth()->user());

        Notification::make()->success()->title(__('filament-cms::cms.saved'))->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->restoreAction()];
    }

    /**
     * Brings back an older version of this page. Developers only.
     */
    protected function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('filament-cms::cms.restore'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (): bool => FilamentCmsPlugin::get()->isDeveloper(Filament::auth()->user()))
            ->modalSubmitActionLabel(__('filament-cms::cms.restore_submit'))
            ->schema([
                Select::make('revision')
                    ->label(__('filament-cms::cms.revision'))
                    ->options(fn (): array => $this->revisionOptions())
                    ->in(fn (): array => array_keys($this->revisionOptions()))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $revision = CmsRevision::query()->where('key', $this->key)->findOrFail($data['revision']);

                app(ContentStore::class)->restore($revision, Filament::auth()->user());

                $this->form->fill(ContentForm::fill($this->definition(), $revision->content));

                Notification::make()->success()->title(__('filament-cms::cms.restored'))->send();
            });
    }

    /**
     * @return array<int, string>
     */
    protected function revisionOptions(): array
    {
        return CmsRevision::query()
            ->with('user')
            ->where('key', $this->key)
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (CmsRevision $revision): array => [
                $revision->getKey() => trim($revision->created_at?->timezone(config('app.timezone'))->format('j M Y, g:i a').' · '.($revision->user?->name ?? '')),
            ])
            ->all();
    }

    protected function definition(): CmsPage
    {
        return $this->cms()->definition($this->key);
    }

    protected function cms(): Cms
    {
        return app(Cms::class);
    }

    protected function keyFromRequest(): string
    {
        return (string) request()->query('key', array_key_first(static::editablePages()) ?? '');
    }

    protected function canEdit(string $key): bool
    {
        return array_key_exists($key, static::editablePages());
    }

    /**
     * @return array<string, class-string<CmsPage>>
     */
    protected static function editablePages(): array
    {
        return array_diff_key(app(Cms::class)->pages(), [Cms::SETTINGS => true]);
    }
}
