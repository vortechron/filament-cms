<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Mail\FormSubmissionMail;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormEmailTest extends TestCase
{
    use MakesForms;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'smtp']);
        Mail::fake();
    }

    private function shopEmail(?string $email): void
    {
        app(ContentStore::class)->save('settings', ['contact' => ['phones' => ['012-345 6789'], 'email' => $email]], null);
    }

    public function test_the_email_action_sends_the_fields_to_the_shop_email(): void
    {
        $this->shopEmail('kedai@example.com');
        $page = $this->pageWithForm(self::exampleForm([['type' => 'email', 'to' => '@site.email', 'subject' => 'Pertanyaan baru dari website']]));

        $this->send($page, self::goodInput(['nama' => '<b>Siti</b> & Ali', 'mesej' => "Baris satu\nBaris dua"]))
            ->assertRedirect(url('/hubungi').'#form-frm00001')
            ->assertSessionHas('filament-cms.form_sent', 'frm00001');

        Mail::assertSent(FormSubmissionMail::class, function (FormSubmissionMail $mail): bool {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('kedai@example.com'));
            $this->assertTrue($mail->hasSubject('Pertanyaan baru dari website'));
            $this->assertStringContainsString('&lt;b&gt;Siti&lt;/b&gt; &amp; Ali', $html);
            $this->assertStringNotContainsString('<b>Siti</b>', $html);
            $this->assertStringContainsString('Baris satu<br />', $html);
            $this->assertStringContainsString('Saya setuju dihubungi.', $html);
            $this->assertStringContainsString('Isnin', $html);
            $this->assertStringContainsString('/hubungi', $html);

            return true;
        });
        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_an_email_to_a_written_address_and_a_reply_to_the_visitor(): void
    {
        $form = self::exampleForm([['type' => 'email', 'to' => 'owner@example.com', 'subject' => '']]);
        $form['fields'][] = ['type' => 'email', 'name' => 'emel', 'label' => 'E-mel'];
        $page = $this->pageWithForm($form);

        $this->send($page, self::goodInput(['emel' => 'siti@example.com']));

        Mail::assertSent(FormSubmissionMail::class, fn (FormSubmissionMail $mail): bool => $mail->hasTo('owner@example.com')
            && $mail->hasReplyTo('siti@example.com')
            && $mail->hasSubject('Borang baru: Borang pertanyaan'));
    }

    public function test_no_email_is_sent_without_an_address_or_with_a_log_mailer_but_the_send_is_kept(): void
    {
        Log::spy();
        $this->shopEmail(null);
        $page = $this->pageWithForm(self::exampleForm([['type' => 'email', 'to' => '@site.email']]));

        $this->send($page)->assertSessionHas('filament-cms.form_sent');
        $this->shopEmail('kedai@example.com');
        config(['mail.default' => 'log']);
        $this->send($page)->assertSessionHas('filament-cms.form_sent');
        config(['mail.default' => 'array']);
        $this->send($page)->assertSessionHas('filament-cms.form_sent');

        Mail::assertNothingSent();
        $this->assertSame(3, FormSubmission::query()->count());
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, 'email skipped'))->times(3);
    }

    public function test_a_failing_mailer_never_loses_the_send(): void
    {
        $this->shopEmail('kedai@example.com');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $page = $this->pageWithForm(self::exampleForm([['type' => 'email', 'to' => '@site.email']]));

        $this->send($page)->assertRedirect(url('/hubungi').'#form-frm00001')->assertSessionHas('filament-cms.form_sent');

        $this->assertSame(1, FormSubmission::query()->count());
    }
}
