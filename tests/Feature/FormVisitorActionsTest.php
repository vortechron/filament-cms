<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormVisitorActionsTest extends TestCase
{
    use MakesForms;

    private function shopPhone(?string $phone): void
    {
        app(ContentStore::class)->save('settings', ['contact' => ['phones' => $phone === null ? [] : [$phone]]], null);
    }

    public function test_the_whatsapp_action_sends_the_visitor_to_the_shop_with_the_message_filled_in(): void
    {
        $this->shopPhone('012-345 6789');
        $page = $this->pageWithForm(self::exampleForm([['type' => 'whatsapp', 'to' => '@site.whatsapp_number', 'message' => 'Hai, saya {nama}. {mesej} Hari: {hari}. {tiada}']]));

        $response = $this->send($page, self::goodInput(['nama' => 'Siti & Ali?', 'mesej' => "Harga?\n#1"]));

        $response->assertRedirect('https://wa.me/60123456789?text='.rawurlencode("Hai, saya Siti & Ali?. Harga?\n#1 Hari: Isnin. {tiada}"));
        $response->assertSessionHas('filament-cms.form_sent', 'frm00001');
        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_a_written_number_and_an_empty_message_list_the_fields(): void
    {
        $page = $this->pageWithForm(self::exampleForm([['type' => 'whatsapp', 'to' => '+60 12-999 8888', 'message' => '']]));

        $location = $this->send($page)->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/60129998888?text=', $location);
        $this->assertSame("Nama: Siti\nTelefon: 012-345 6789\nServis: Parcel\nMesej: Berapa harga?\nHari: Isnin\nSaya setuju dihubungi.: Ya", rawurldecode(substr($location, strlen('https://wa.me/60129998888?text='))));
    }

    public function test_without_a_shop_number_the_visitor_goes_back_to_the_form(): void
    {
        $this->shopPhone(null);
        $page = $this->pageWithForm(self::exampleForm([['type' => 'whatsapp', 'to' => '@site.whatsapp_number']]));

        $this->send($page)->assertRedirect(url('/hubungi').'#form-frm00001');
        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_the_redirect_action_sends_the_visitor_to_the_stored_link(): void
    {
        $here = $this->pageWithForm(self::exampleForm([['type' => 'redirect', 'url' => '/terima-kasih']]));
        $away = $this->pageWithForm(self::exampleForm([['type' => 'redirect', 'url' => 'https://example.com/ok']]), path: '/luar');
        $mail = $this->pageWithForm(self::exampleForm([['type' => 'redirect', 'url' => 'mailto:kedai@example.com']]), path: '/emel');

        $this->send($here)->assertRedirect(url('/terima-kasih'))->assertSessionHas('filament-cms.form_sent', 'frm00001');
        $this->send($away)->assertRedirect('https://example.com/ok');
        $this->send($mail)->assertRedirect(url('/emel').'#form-frm00001');
    }

    public function test_whatsapp_wins_over_a_redirect(): void
    {
        $this->shopPhone('012-345 6789');
        $page = $this->pageWithForm(self::exampleForm([['type' => 'redirect', 'url' => '/terima-kasih'], ['type' => 'whatsapp', 'to' => '@site.whatsapp_number', 'message' => 'Hai']]));

        $this->send($page)->assertRedirect('https://wa.me/60123456789?text=Hai');
    }
}
