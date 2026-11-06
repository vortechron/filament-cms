<?php

namespace Vortechron\FilamentCms\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Vortechron\FilamentCms\Access\CmsAccess;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsSettingsTranslations;
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

    protected function getHeaderActions(): array
    {
        $actions = parent::getHeaderActions();
        if (! SiteLanguages::enabled()) {
            return $actions;
        }
        foreach (SiteLanguages::labels() as $locale => $label) {
            if ($locale === SiteLanguages::default()) {
                continue;
            }
            $fields = [];
            foreach (CmsSettingsTranslations::fields() as $section => $names) {
                foreach ($names as $name) {
                    $fields[] = Textarea::make("values.{$section}.{$name}")->label($this->translationFieldLabel($section, $name))->maxLength(10000);
                }
            }
            $actions[] = Action::make('translate_'.$locale)
                ->label(__('filament-cms::languages.settings_action', ['language' => $label]))
                ->modalDescription(__('filament-cms::languages.settings_help'))
                ->schema($fields)
                ->fillForm(fn (): array => ['values' => CmsSettingsTranslations::saved($locale)])
                ->action(function (array $data) use ($locale): void {
                    abort_unless(static::canAccess(), 403);
                    app(CmsSettingsTranslations::class)->save($locale, $data['values'] ?? [], Filament::auth()->user());
                    Notification::make()->success()->title(__('filament-cms::cms.saved'))->send();
                });
        }

        return $actions;
    }

    private function translationFieldLabel(string $sectionKey, string $name): string
    {
        foreach ($this->definition()->sections() as $section) {
            if ($section->key !== $sectionKey) {
                continue;
            }
            foreach ($section->fields() as $field) {
                if ($field instanceof Field && $field->getName() === $name) {
                    return $section->label().' / '.$field->getLabel();
                }
            }
        }

        return __('filament-cms::languages.text');
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
