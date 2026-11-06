<?php

namespace Vortechron\FilamentCms\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsSettingsTranslations;
use Vortechron\FilamentCms\ContentForm;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Filament\Schemas\TranslatableText;
use Vortechron\FilamentCms\Models\CmsContent;
use Vortechron\FilamentCms\SiteLanguages;

/**
 * The site-wide settings: /admin/settings.
 */
class EditSettings extends EditContent
{
    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /**
     * @return array<NavigationItem>
     */
    public static function canAccess(): bool
    {
        return CmsAccess::allows('settings');
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(app(Cms::class)->definition(Cms::SETTINGS)::label())
                ->group(static::getNavigationGroup())
                ->icon(static::$navigationIcon)
                ->sort(100)
                ->url(static::getUrl())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteName())),
        ];
    }

    #[Locked]
    public array $translationBaseline = [];

    #[Locked]
    public array $settingsBaseline = [];

    public function mount(): void
    {
        parent::mount();
        if (SiteLanguages::enabled()) {
            foreach (SiteLanguages::labels() as $locale => $label) {
                if ($locale !== SiteLanguages::default()) {
                    $this->translationBaseline[$locale] = CmsSettingsTranslations::saved($locale);
                }
            }
            $base = SiteLanguages::run(SiteLanguages::default(), fn () => $this->cms()->saved($this->key));
            $this->settingsBaseline = ContentForm::fill($this->definition(), $base);
            $this->form->fill([...$this->settingsBaseline, 'translations' => $this->translationBaseline]);
        }
    }

    public function form(Schema $schema): Schema
    {
        if (! SiteLanguages::enabled()) {
            return parent::form($schema);
        }
        $sections = [];
        foreach ($this->definition()->sections() as $section) {
            $fields = [];
            foreach ($section->fields() as $field) {
                $name = $field instanceof Field ? $field->getName() : null;
                if ($name === null) {
                    $fields[] = Group::make([$field])->statePath($section->key);

                    continue;
                }
                $field->name($section->key.'.'.$name)->statePath($section->key.'.'.$name);
                if (in_array($name, CmsSettingsTranslations::fields()[$section->key] ?? [], true)
                    && ($field instanceof TextInput || $field instanceof Textarea)) {
                    $fields[] = TranslatableText::field($field, function (string $locale) use ($field, $section, $name) {
                        $translated = clone $field;

                        return $translated->name("translations.{$locale}.{$section->key}.{$name}")->statePath("translations.{$locale}.{$section->key}.{$name}")->required(false)->default(null);
                    });
                } else {
                    $fields[] = $field;
                }
            }
            $sections[] = Section::make($section->label())
                ->description($section->description())->collapsible()->schema($fields);
        }

        return $schema->components($sections)->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        if (! SiteLanguages::enabled()) {
            parent::save();

            return;
        }
        $data = $this->form->getState();
        $translations = $data['translations'] ?? [];
        unset($data['translations']);
        DB::transaction(function () use ($data, $translations): void {
            foreach ($translations as $locale => $sections) {
                $changes = [];
                foreach ($sections as $section => $fields) {
                    foreach ($fields as $field => $value) {
                        if ($value !== ($this->translationBaseline[$locale][$section][$field] ?? null)) {
                            $changes[$section][$field] = $value;
                        }
                    }
                }
                if ($changes !== []) {
                    app(CmsSettingsTranslations::class)->save($locale, $changes, Filament::auth()->user(), expectedValues: $this->translationBaseline[$locale] ?? []);
                }
            }
            $row = CmsContent::where('key', $this->key)->lockForUpdate()->first();
            $saved = ContentForm::fill($this->definition(), $row?->content ?? []);
            foreach ($data as $section => $fields) {
                foreach ($fields as $field => $value) {
                    if ($value !== ($this->settingsBaseline[$section][$field] ?? null)) {
                        $saved[$section][$field] = $value;
                    }
                }
            }
            app(ContentStore::class)->save($this->key, $saved, Filament::auth()->user());
        });
        $this->translationBaseline = $translations;
        $this->settingsBaseline = $data;
        Notification::make()->success()->title(__('filament-cms::cms.saved'))->send();
    }

    protected function keyFromRequest(): string
    {
        return Cms::SETTINGS;
    }

    protected function canEdit(string $key): bool
    {
        return $key === Cms::SETTINGS && app(Cms::class)->has($key);
    }
}
