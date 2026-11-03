<?php

namespace Vortechron\FilamentCms\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email a form's email action sends the shop: each field's label and
 * value, and the page it was sent from. Sent at once (not queued). Every
 * value is escaped by the view.
 */
class FormSubmissionMail extends Mailable
{
    /**
     * @param  list<array{name: string, label: string, value: string}>  $rows
     */
    public function __construct(
        public readonly string $formName,
        public readonly array $rows,
        public readonly ?string $pagePath,
        private readonly string $subjectLine,
        private readonly ?string $visitorEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->visitorEmail === null ? [] : [new Address($this->visitorEmail)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'filament-cms::mail.form-submission');
    }
}
