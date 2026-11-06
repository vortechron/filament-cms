<?php

namespace Vortechron\FilamentCms\Filament\Schemas;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Js;
use Vortechron\FilamentCms\SiteLanguages;

/** Real scalar fields share one local language selector; hidden languages still validate. */
class TranslatableText extends Group
{
    protected string $fieldName = '';

    protected string $fieldLabel = '';

    public static function field(Field $original, Closure $translated): Field|static
    {
        if (! SiteLanguages::enabled()) {
            return $original;
        }

        $fields = [];
        foreach (SiteLanguages::labels() as $locale => $label) {
            $field = $locale === SiteLanguages::default() ? $original : $translated($locale)
                ->dehydrateStateUsing(fn ($livewire, Field $component) => data_get($livewire, $component->getStatePath()));
            $fields[] = Group::make([$field])->extraAttributes([
                'x-show' => 'cmsFieldLocale === '.Js::from($locale)->toHtml(),
                'x-cloak' => true,
                'data-cms-locale' => $locale,
            ]);
        }
        $group = static::make($fields)->columnSpanFull();
        $group->fieldName = $original->getName();
        $group->fieldLabel = (string) $original->getLabel();

        return $group;
    }

    public function toEmbeddedHtml(): string
    {
        return view('filament-cms::filament.translatable-text', [
            'schema' => $this->getChildSchema(),
            'fieldName' => $this->fieldName,
            'fieldLabel' => $this->fieldLabel,
            'defaultLocale' => SiteLanguages::default(),
            'locales' => SiteLanguages::labels(),
        ])->render();
    }
}
