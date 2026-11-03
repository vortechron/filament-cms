<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Mail\FormSubmissionMail;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * Runs a saved submission's stored actions: the email to the shop, then
 * where the visitor goes (VisitorRedirect). A failing action is logged and
 * never loses the submission.
 */
final class FormActions
{
    private const QUIET_MAILERS = ['log', 'array'];

    public function __construct(private readonly DynamicValues $values, private readonly VisitorRedirect $visitor) {}

    /**
     * @return string|null where to send the visitor, or null for back to the form
     */
    public function run(FormSubmission $submission, LocatedForm $found, RenderContext $context): ?string
    {
        $email = $found->form->action('email');

        if ($email !== null) {
            $this->attempt('email', $submission, fn () => $this->email($submission, $found, $email, $context));
        }

        return $this->attempt('redirect', $submission, fn (): ?string => $this->visitor->url($submission, $found, $context));
    }

    /**
     * @param  array<string, string>  $action
     */
    private function email(FormSubmission $submission, LocatedForm $found, array $action, RenderContext $context): void
    {
        $to = self::emailAddress($this->resolve($action['to'] ?? '', $context));
        $mailer = config('mail.default');

        if ($to === null || in_array($mailer, self::QUIET_MAILERS, true)) {
            Log::info('Form email skipped: '.($to === null ? 'no address' : "the {$mailer} mailer").'.', ['submission' => $submission->getKey()]);

            return;
        }

        $name = $found->form->name() !== '' ? $found->form->name() : __('filament-cms::forms.form');
        $subject = ($action['subject'] ?? '') !== '' ? $action['subject'] : __('filament-cms::forms.mail_subject', ['form' => $name]);

        Mail::to($to)->send(new FormSubmissionMail(
            $name,
            SubmissionRows::make($found->form, $submission->data),
            $submission->meta['page'] ?? null,
            $subject,
            $this->visitorEmail($found, $submission),
        ));
    }

    /**
     * The first email field the visitor filled, to reply to.
     */
    private function visitorEmail(LocatedForm $found, FormSubmission $submission): ?string
    {
        foreach ($found->form->fields() as $field) {
            if ($field['type'] === 'email' && ($address = self::emailAddress($submission->data[$field['name']] ?? null)) !== null) {
                return $address;
            }
        }

        return null;
    }

    /**
     * A stored value, or the dynamic value it refers to (`@site.email`).
     */
    private function resolve(string $value, RenderContext $context): ?string
    {
        $reference = DynamicValues::reference($value);

        return $reference === null ? $value : $this->values->resolve(substr($reference, 1), $context);
    }

    private static function emailAddress(mixed $value): ?string
    {
        return is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false ? trim($value) : null;
    }

    private function attempt(string $type, FormSubmission $submission, callable $action): mixed
    {
        try {
            return $action();
        } catch (Throwable $exception) {
            Log::warning("Form {$type} action failed.", ['submission' => $submission->getKey(), 'error' => $exception::class.': '.$exception->getMessage()]);

            return null;
        }
    }
}
