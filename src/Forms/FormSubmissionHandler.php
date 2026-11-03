<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Vortechron\FilamentBlockEditor\Forms\FormTimestamp;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageRenderer;

/**
 * Handles a visitor sending a form block (POST /_cms/forms/{document}):
 * finds the stored form, checks the input with the stored fields, saves
 * it, then sends the visitor back to the form with its success message.
 */
final class FormSubmissionHandler
{
    public const FLASH = 'filament-cms.form_sent';

    /**
     * A form sent sooner than this after the page was drawn is a bot.
     */
    public const MIN_SECONDS = 2;

    /**
     * Sends a minute from one address to one form.
     */
    public const PER_MINUTE = 5;

    public function __construct(private readonly FormLocator $locator, private readonly FormActions $actions) {}

    public function handle(Document $document, Request $request): Response
    {
        $found = $this->locator->find($document, $request->input('_form'));

        abort_if($found === null, 404);

        $back = self::pageUrl($document).'#form-'.$found->blockId;
        $spam = self::spam($request);

        if ($spam !== null) {
            Log::info("Form submission refused: {$spam}.", ['document' => $document->getKey(), 'form' => $found->key]);

            return redirect()->to($back);
        }

        if (self::tooMany($request, $found)) {
            Log::info('Form submission refused: too many sends.', ['document' => $document->getKey(), 'form' => $found->key]);

            return redirect()->to($back)->withErrors(self::formError($found, __('filament-cms::forms.try_again')))->withInput(self::oldInput($found, $request));
        }

        $validator = self::validator($found, $request);

        if ($validator->fails()) {
            return redirect()->to($back)->withErrors(self::errors($found, $validator->errors()->toArray()))->withInput(self::oldInput($found, $request));
        }

        $submission = $this->save($document, $found, $request, self::data($found, $validator->validated()));
        $next = $this->actions->run($submission, $found, self::context($document));

        return redirect()->to($next ?? $back)->with(self::FLASH, $found->blockId);
    }

    /**
     * Why the send looks like a bot's, or null: the hidden `_website` field
     * was filled, or `_t` is not one this app signed or is too young.
     */
    private static function spam(Request $request): ?string
    {
        if ($request->filled('_website')) {
            return 'honeypot filled';
        }

        $age = FormTimestamp::age($request->input('_t'));

        return match (true) {
            $age === null => 'time not valid',
            $age < self::MIN_SECONDS => 'sent too fast',
            default => null,
        };
    }

    /**
     * Counts this send. True when the address already sent this form
     * PER_MINUTE times in the last minute.
     */
    private static function tooMany(Request $request, LocatedForm $found): bool
    {
        $key = 'filament-cms-form:'.hash('sha256', $request->ip().'|'.$found->blockId);

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    /**
     * A message for the whole form, shown under its first field (the form
     * block shows errors by field only).
     *
     * @return array<string, string>
     */
    private static function formError(LocatedForm $found, string $message): array
    {
        $first = $found->form->fields()[0]['name'] ?? null;

        return ['form.'.$found->blockId.($first === null ? '' : '.'.$first) => $message];
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

    /**
     * The values a page is drawn with, so `@site.email` and `page.*`
     * resolve as they did on the page.
     */
    private static function context(Document $document): RenderContext
    {
        return new RenderContext(values: $document instanceof SitePage
            ? SitePageRenderer::pageValues($document)
            : ['page.title' => (string) $document->title, 'page.url' => (string) $document->url(), CmsFormEndpoint::VALUE => (string) $document->getKey()]);
    }

    private static function pageUrl(Document $document): string
    {
        return $document->url() ?? url('/');
    }
}
