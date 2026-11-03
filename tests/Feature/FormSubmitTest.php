<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormSubmitTest extends TestCase
{
    use MakesForms;

    public function test_a_sent_form_is_saved_and_the_visitor_sees_the_success_message(): void
    {
        $page = $this->pageWithForm();

        $this->withHeader('User-Agent', str_repeat('Mozilla ', 60))
            ->send($page)
            ->assertRedirect(url('/hubungi').'#form-frm00001')
            ->assertSessionHas('filament-cms.form_sent', 'frm00001')
            ->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();
        $this->assertSame($page->getKey(), $submission->document_id);
        $this->assertSame('sec00001.frm00001', $submission->form_key);
        $this->assertSame('Borang pertanyaan', $submission->form_name);
        $this->assertSame(['nama' => 'Siti', 'telefon' => '012-345 6789', 'servis' => 'Parcel', 'mesej' => 'Berapa harga?', 'hari' => ['Isnin'], 'setuju' => true], $submission->data);
        $this->assertSame(FormSubmission::NEW, $submission->fresh()->status);
        $this->assertSame('/hubungi', $submission->meta['page']);
        $this->assertSame(300, mb_strlen($submission->meta['user_agent']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $submission->meta['ip_hash']);
        $this->assertNotSame(hash('sha256', '127.0.0.1'), $submission->meta['ip_hash']);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($submission->meta));

        $this->get('/hubungi')->assertOk()->assertSee('Terima kasih.')->assertDontSee('name="nama"', false);
    }

    public function test_bad_input_goes_back_to_the_form_with_errors_and_the_values_sent(): void
    {
        $page = $this->pageWithForm();

        $response = $this->send($page, self::goodInput(['nama' => '', 'servis' => 'Hack', 'hari' => ['Ahad'], 'setuju' => null, 'is_admin' => '1']));

        $response->assertRedirect(url('/hubungi').'#form-frm00001')
            ->assertSessionHasErrors(['form.frm00001.nama', 'form.frm00001.servis', 'form.frm00001.hari', 'form.frm00001.setuju'])
            ->assertSessionMissing('filament-cms.form_sent');
        $this->assertSame(['_form' => 'sec00001.frm00001', 'telefon' => '012-345 6789', 'servis' => 'Hack', 'mesej' => 'Berapa harga?', 'hari' => ['Ahad']], session()->getOldInput());
        $this->assertSame('Nama perlu diisi.', session('errors')->first('form.frm00001.nama'));
        $this->assertSame(0, FormSubmission::query()->count());

        $html = $this->get('/hubungi')->assertOk()->getContent();
        $this->assertStringContainsString('Nama perlu diisi.', $html);
        $this->assertStringContainsString('value="012-345 6789"', $html);
    }

    public function test_fields_a_form_does_not_have_are_not_saved(): void
    {
        $page = $this->pageWithForm();

        $this->send($page, [...self::goodInput(), 'is_admin' => '1', 'status' => 'read', 'mesej' => null]);

        $this->assertSame(['nama', 'telefon', 'servis', 'mesej', 'hari', 'setuju'], array_keys(FormSubmission::query()->sole()->data));
        $this->assertNull(FormSubmission::query()->sole()->data['mesej']);
        $this->assertSame(FormSubmission::NEW, FormSubmission::query()->sole()->status);
    }
}
