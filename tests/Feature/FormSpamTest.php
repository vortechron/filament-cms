<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormSpamTest extends TestCase
{
    use MakesForms;

    private function assertQuietlyRefused(SitePage $page, array $hidden): void
    {
        $this->send($page, hidden: $hidden)
            ->assertRedirect(url('/hubungi').'#form-frm00001')
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('filament-cms.form_sent');

        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_a_filled_honeypot_is_quietly_refused_and_logged(): void
    {
        Log::spy();

        $this->assertQuietlyRefused($this->pageWithForm(), ['_website' => 'https://spam.example']);

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'honeypot') && $context['form'] === 'sec00001.frm00001')->once();
    }

    public function test_a_form_sent_too_fast_is_quietly_refused(): void
    {
        $this->assertQuietlyRefused($this->pageWithForm(), ['_t' => $this->timestamp(1)]);
    }

    public function test_a_missing_or_forged_time_is_quietly_refused(): void
    {
        $page = $this->pageWithForm();
        [$time] = explode('.', $this->timestamp(60));

        $this->assertQuietlyRefused($page, ['_t' => '']);
        $this->assertQuietlyRefused($page, ['_t' => $time.'.'.str_repeat('0', 64)]);
        $this->assertQuietlyRefused($page, ['_t' => ['x']]);
    }

    public function test_a_form_sent_after_two_seconds_is_kept(): void
    {
        $this->send($this->pageWithForm(), hidden: ['_t' => $this->timestamp(2)])->assertSessionHas('filament-cms.form_sent');

        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_more_than_five_sends_a_minute_from_one_address_get_a_try_again_error(): void
    {
        $page = $this->pageWithForm();
        $this->part('footer', [self::formSection('ftr00001', 'frmftr01')]);

        for ($i = 0; $i < 5; $i++) {
            $this->send($page)->assertSessionHasNoErrors();
        }

        $this->send($page)
            ->assertRedirect(url('/hubungi').'#form-frm00001')
            ->assertSessionHasErrors(['form.frm00001.nama' => 'Cuba lagi sebentar.']);
        $this->assertSame('Siti', session()->getOldInput('nama'));
        $this->assertSame(5, FormSubmission::query()->count());

        $this->send($page, key: 'ftr00001.frmftr01')->assertSessionHasNoErrors();
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->send($page)->assertSessionHasNoErrors();
        $this->assertSame(7, FormSubmission::query()->count());

        $this->travel(61)->seconds();
        $this->send($page)->assertSessionHasNoErrors();
        $this->assertSame(8, FormSubmission::query()->count());
    }
}
