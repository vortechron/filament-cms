<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Models\CmsContent;
use Vortechron\FilamentCms\Settings\SettingsSection;

/**
 * The shop info the page builder shows next to the header and footer: the
 * settings sections that declare builderFields(), with their values. A
 * save from there is checked with each section's builderRules(), cleaned
 * by its fields, and saved through the same ContentStore as the Tetapan
 * screen, so it keeps a version. Keys the builder does not show are kept.
 * previewSettings() cleans unsaved values the same way for the preview.
 *
 * Picture fields (the logo) are paths on the CMS disk. The builder gets
 * their address. A new picture must be a JPG, PNG, WebP or GIF directly in
 * CmsImage's folder or the builder's upload folder on that disk.
 */
final class SiteInfo
{
    public function __construct(private readonly Cms $cms, private readonly ContentStore $store) {}

    /**
     * `['groups' => [['key', 'label', 'fields' => [Field, ...]]], 'values' => [group => [field => value]]]`,
     * or null when no settings section shows in the builder.
     *
     * @return array{groups: list<array{key: string, label: string, fields: list<Field>}>, values: array<string, array<string, mixed>>}|null
     */
    public function forBuilder(): ?array
    {
        $sections = $this->sections();

        if ($sections === []) {
            return null;
        }

        $saved = $this->cms->saved(Cms::SETTINGS);
        $groups = [];
        $values = [];

        foreach ($sections as $key => $section) {
            $groups[] = ['key' => $key, 'label' => $section->label(), 'fields' => $section->builderFields()];
            $values[$key] = $this->builderValues($section, $saved);
        }

        return ['groups' => $groups, 'values' => $values];
    }

    /**
     * @param  array<string, mixed>  $values  by group, as the builder sends them
     *
     * @throws ValidationException with errors keyed "group.field"
     */
    public function save(array $values, ?Authenticatable $user): void
    {
        $locale = SiteLanguages::current();
        $translated = [];
        if (SiteLanguages::enabled() && $locale !== SiteLanguages::default()) {
            foreach (CmsSettingsTranslations::fields() as $section => $fields) {
                foreach ($fields as $field) {
                    if (array_key_exists($field, $values[$section] ?? [])) {
                        $translated[$section][$field] = $values[$section][$field];
                        unset($values[$section][$field]);
                    }
                }
            }
        }

        DB::transaction(function () use ($locale, $translated, $values, $user): void {
            $stored = CmsContent::query()->where('key', Cms::SETTINGS)->lockForUpdate()->first();
            $saved = $stored?->content ?? [];
            $errors = [];
            $changes = [];
            foreach ($this->sections() as $key => $section) {
                if (! is_array($values[$key] ?? null) || $values[$key] === []) {
                    continue;
                }
                ['values' => $changes[$key], 'errors' => $groupErrors] = $this->clean($section, $values[$key], $saved);
                foreach ($groupErrors as $field => $message) {
                    $errors["{$key}.{$field}"] = $message;
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            if ($translated !== []) {
                app(CmsSettingsTranslations::class)->save($locale, $translated, $user);
            }
            if ($changes !== []) {
                $this->store->save(Cms::SETTINGS, self::merged($saved, $changes), $user);
            }
        });
    }

    /**
     * The whole settings content with the builder's unsaved shop info
     * cleaned as save() cleans it and merged over the stored settings, for
     * a preview (Cms::withSettings()). A field that fails the checks keeps
     * its stored value; the other fields still show. Nothing is saved.
     *
     * @param  array<string, mixed>  $values  by group, as the builder sends them
     * @return array<string, array<string, mixed>>
     */
    public function previewSettings(array $values): array
    {
        $saved = $this->cms->saved(Cms::SETTINGS);
        $changes = [];

        foreach ($this->sections() as $key => $section) {
            if (! is_array($values[$key] ?? null)) {
                continue;
            }

            $result = $this->clean($section, $values[$key], $saved);

            if ($result['errors'] !== []) {
                $failed = array_map(fn (string $path): string => explode('.', $path)[0], array_keys($result['errors']));
                $result = $this->clean($section, array_diff_key($values[$key], array_flip($failed)), $saved);
            }

            $changes[$key] = $result['values'];
        }

        return self::merged($saved, $changes);
    }

    /**
     * @return array<string, SettingsSection>
     */
    private function sections(): array
    {
        if (! $this->cms->has(Cms::SETTINGS)) {
            return [];
        }

        $sections = [];

        foreach ($this->cms->definition(Cms::SETTINGS)->sections() as $section) {
            if ($section instanceof SettingsSection && $section->builderFields() !== []) {
                $sections[$section->key] = $section;
            }
        }

        return $sections;
    }

    /**
     * One group as the builder sent it, checked and cleaned as it is saved.
     * Fields the builder does not show keep their stored value.
     *
     * @param  array<mixed>  $submitted
     * @param  array<string, mixed>  $saved
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    private function clean(SettingsSection $section, array $submitted, array $saved): array
    {
        $fields = $section->builderFields();
        $names = array_flip(array_map(fn (Field $field): string => $field->name, $fields));
        $input = [...$this->builderValues($section, $saved), ...array_intersect_key($submitted, $names)];
        $errors = self::check($input, $section->builderRules(), $fields);
        $clean = $this->pictures($fields, Field::cleanFields($fields, $input), $saved[$section->key] ?? [], $errors);

        return ['values' => $section->fromBuilderValues(self::emptyAsNull($fields, $clean)), 'errors' => $errors];
    }

    /**
     * The stored settings with each group's changes over it; keys the
     * builder does not show are kept.
     *
     * @param  array<string, mixed>  $saved
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array<string, mixed>>
     */
    private static function merged(array $saved, array $changes): array
    {
        foreach ($changes as $key => $change) {
            $saved[$key] = [...(is_array($saved[$key] ?? null) ? $saved[$key] : []), ...$change];
        }

        return $saved;
    }

    /**
     * The saved values on top of the defaults, as the builder's fields hold them.
     *
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    private function builderValues(SettingsSection $section, array $saved): array
    {
        $values = $section->toBuilderValues(PageContent::merge($this->cms->definition(Cms::SETTINGS), $section, $saved[$section->key] ?? []));

        foreach ($section->builderFields() as $field) {
            if ($field->type === 'image') {
                $values[$field->name] = cms_image($values[$field->name] ?? null);
            }
        }

        return Field::cleanFields($section->builderFields(), $values);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @param  list<Field>  $fields
     * @return array<string, string>
     */
    private static function check(array $input, array $rules, array $fields): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $field = $field->toArray();
            $attributes[$field['name']] = $field['label'];

            foreach ($field['fields'] ?? [] as $child) {
                $attributes["{$field['name']}.*.{$child['name']}"] = $child['label'];
            }
        }

        $validator = Validator::make($input, $rules, [], $attributes);

        return $validator->fails()
            ? array_map(fn (array $messages): string => (string) ($messages[0] ?? ''), $validator->errors()->messages())
            : [];
    }

    /**
     * Each picture as a path on the CMS disk: kept when unchanged, null when
     * removed, and refused unless it was uploaded to that disk.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $clean
     * @param  array<string, mixed>  $stored
     * @param  array<string, string>  $errors
     * @return array<string, mixed>
     */
    private function pictures(array $fields, array $clean, array $stored, array &$errors): array
    {
        foreach ($fields as $field) {
            $url = $clean[$field->name] ?? null;

            if ($field->type !== 'image' || $url === null || $url === '') {
                continue;
            }

            $clean[$field->name] = $url === cms_image($stored[$field->name] ?? null)
                ? $stored[$field->name]
                : $this->uploadedPicture($url);

            if ($clean[$field->name] === null) {
                $errors[$field->name] = __('filament-cms::settings.picture_here');
            }
        }

        return $clean;
    }

    /**
     * The path on the CMS disk a new picture's address points at, or null
     * unless it is a file of CmsImage's types directly inside a folder the
     * CMS or the builder uploads to. Nothing is decoded: a name holds only
     * letters, digits, dots, dashes and underscores, so "..", "%2e" and
     * "//" never pass.
     */
    private function uploadedPicture(string $url): ?string
    {
        $disk = (string) config('filament-cms.disk', 'public');
        $root = rtrim(Storage::disk($disk)->url(''), '/').'/';
        $path = str_starts_with($url, $root) ? substr($url, strlen($root)) : '';
        $slash = strrpos($path, '/');

        if ($slash === false || ! in_array(substr($path, 0, $slash), $this->uploadFolders($disk), true)) {
            return null;
        }

        $types = implode('|', CmsImage::EXTENSIONS);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)*\.(?:'.$types.')$/i', substr($path, $slash + 1)) !== 1) {
            return null;
        }

        return Storage::disk($disk)->exists($path) ? $path : null;
    }

    /**
     * CmsImage's folder, and the builder's upload folder when the builder
     * uploads to the CMS disk.
     *
     * @return list<string>
     */
    private function uploadFolders(string $disk): array
    {
        $folders = [CmsImage::DIRECTORY];

        if ((string) config('filament-block-editor.disk', 'public') === $disk) {
            $folders[] = trim((string) config('filament-block-editor.directory', 'block-editor'), '/');
        }

        return $folders;
    }

    /**
     * An empty text, link or picture is saved as null, as the Tetapan screen does.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function emptyAsNull(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            if ($field->type !== 'list' && ($values[$field->name] ?? null) === '') {
                $values[$field->name] = null;
            }
        }

        return $values;
    }
}
