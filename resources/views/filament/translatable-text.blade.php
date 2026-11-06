<div x-data="{ cmsFieldLocale: @js($defaultLocale) }" data-cms-field-language="{{ $fieldName }}">
    @foreach ($schema->getComponents() as $languageGroup)
        <div {{ $languageGroup->getExtraAttributeBag() }}>
            {{ $languageGroup->getChildSchema() }}
        </div>
    @endforeach
    <div style="display: flex; justify-content: flex-end">
        <label style="display: inline-flex; align-items: center; min-height: 44px">
            <span class="sr-only">{{ __('filament-cms::languages.language') }} — {{ $fieldLabel }}</span>
            <select x-model="cmsFieldLocale" aria-label="{{ __('filament-cms::languages.language') }} — {{ $fieldLabel }}" style="min-height: 44px; min-width: 44px; padding: 8px; border: 0; background: transparent; color: inherit; font-size: 12px; text-decoration: underline; cursor: pointer">
                @foreach ($locales as $locale => $label)
                    <option value="{{ $locale }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
</div>
