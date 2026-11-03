<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Support\Str;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * Where the visitor goes after a send, from the stored actions: WhatsApp
 * first (a wa.me link with the message filled in), then a redirect the
 * link rules allow. Null means back to the form.
 */
final class VisitorRedirect
{
    private const MAX_MESSAGE = 2000;

    public function __construct(private readonly DynamicValues $values) {}

    public function url(FormSubmission $submission, LocatedForm $found, RenderContext $context): ?string
    {
        $whatsapp = $found->form->action('whatsapp');
        $redirect = $found->form->action('redirect');

        return ($whatsapp === null ? null : $this->whatsapp($whatsapp, $submission, $found, $context))
            ?? ($redirect === null ? null : $this->redirect($redirect['url'] ?? '', $context));
    }

    /**
     * @param  array<string, string>  $action
     */
    private function whatsapp(array $action, FormSubmission $submission, LocatedForm $found, RenderContext $context): ?string
    {
        $to = $action['to'] ?? '';
        $reference = DynamicValues::reference($to);
        $number = cms_whatsapp_number($reference === null ? $to : $this->values->resolve(substr($reference, 1), $context));

        if ($number === null || preg_match('/^\d{8,15}$/', $number) !== 1) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(Str::limit(self::message($action['message'] ?? '', $submission, $found), self::MAX_MESSAGE, ''));
    }

    /**
     * The stored message with each `{name}` filled with what the visitor
     * sent (plain text), or every field as "Label: value" when it is empty.
     */
    private static function message(string $message, FormSubmission $submission, LocatedForm $found): string
    {
        if (trim($message) === '') {
            return implode("\n", array_map(fn (array $row): string => $row['label'].': '.$row['value'], SubmissionRows::make($found->form, $submission->data)));
        }

        return (string) preg_replace_callback('/\{([a-z][a-z0-9_]{0,39})\}/', fn (array $match): string => array_key_exists($match[1], $submission->data)
            ? SubmissionRows::text($submission->data[$match[1]])
            : $match[0], $message);
    }

    /**
     * A page on this site or an http(s) address; other links (mailto:,
     * tel:, #) are no place to send a visitor after a send.
     */
    private function redirect(string $url, RenderContext $context): ?string
    {
        $link = $this->values->link($url, $context);

        return match (true) {
            $link === null => null,
            preg_match('~^/(?!/)~', $link) === 1 => url($link),
            preg_match('~^https?://~i', $link) === 1 => $link,
            default => null,
        };
    }
}
