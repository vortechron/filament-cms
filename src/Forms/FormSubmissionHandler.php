<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * Handles a visitor sending a form block (POST /_cms/forms/{document}):
 * finds the stored form, checks the input with the stored fields, saves
 * it, then sends the visitor back to the form with its success message.
 */
final class FormSubmissionHandler
{
    public const FLASH = 'filament-cms.form_sent';

    public function __construct(private readonly FormLocator $locator) {}

    public function handle(Document $document, Request $request): Response
    {
        $found = $this->locator->find($document, $request->input('_form'));

        abort_if($found === null, 404);

        $back = self::pageUrl($document).'#form-'.$found->blockId;
        $validator = self::validator($found, $request);

        if ($validator->fails()) {
            return redirect()->to($back)->withErrors(self::errors($found, $validator->errors()->toArray()))->withInput(self::oldInput($found, $request));
        }

        $submission = $this->save($document, $found, $request, self::data($found, $validator->validated()));

        return redirect()->to($back)->with(self::FLASH, $found->blockId);
    }

    /**
     * The stored form's rules, with its labels and plain messages in the
     * site's language.
     */
    private static function validator(LocatedForm $found, Request $request): ValidatorContract
    {
        $labels = [];

        foreach ($found->form->fields() as $field) {
            $label = $field['label'] !== '' ? $field['label'] : $field['name'];
            $labels[$field['name']] = $label;
            $labels[$field['name'].'.*'] = $label;
        }

        return Validator::make($request->all(), $found->form->rules(), (array) __('filament-cms::forms.validation'), $labels);
    }

    /**
     * Each field's messages under the key the form block reads:
     * `form.{blockId}.{name}`.
     *
     * @param  array<string, list<string>>  $messages
     * @return array<string, list<string>>
     */
    private static function errors(LocatedForm $found, array $messages): array
    {
        $errors = [];

        foreach ($messages as $key => $list) {
            $errors['form.'.$found->blockId.'.'.Str::before($key, '.')] ??= $list;
        }

        return $errors;
    }

    /**
     * What the visitor sent, for this form's fields only, so it comes back
     * in this form and no other. Long text is cut.
     *
     * @return array<string, mixed>
     */
    private static function oldInput(LocatedForm $found, Request $request): array
    {
        $old = ['_form' => $found->key];

        foreach ($found->form->fields() as $field) {
            $value = $request->input($field['name']);

            if (is_scalar($value)) {
                $old[$field['name']] = Str::limit((string) $value, 5000, '');
            } elseif (is_array($value)) {
                $old[$field['name']] = array_map(fn (mixed $item): string => is_scalar($item) ? Str::limit((string) $item, 200, '') : '', array_slice(array_values($value), 0, 30));
            }
        }

        return $old;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Document $document, LocatedForm $found, Request $request, array $data): FormSubmission
    {
        return FormSubmission::query()->create([
            'document_id' => $document->getKey(),
            'form_key' => $found->key,
            'form_name' => $found->form->name() !== '' ? $found->form->name() : null,
            'data' => $data,
            'meta' => [
                'page' => $document->path,
                'user_agent' => Str::limit((string) $request->userAgent(), 300, ''),
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            ],
        ]);
    }

    /**
     * The checked values, one per stored field in the form's order.
     *
     * @param  array<string, mixed>  $valid
     * @return array<string, mixed>
     */
    private static function data(LocatedForm $found, array $valid): array
    {
        $data = [];

        foreach ($found->form->fields() as $field) {
            $value = $valid[$field['name']] ?? null;

            $data[$field['name']] = match ($field['type']) {
                'consent' => $value !== null,
                'checkboxes' => is_array($value) ? array_values(array_map(strval(...), $value)) : null,
                default => is_scalar($value) ? (string) $value : null,
            };
        }

        return $data;
    }

    private static function pageUrl(Document $document): string
    {
        return $document->url() ?? url('/');
    }
}
