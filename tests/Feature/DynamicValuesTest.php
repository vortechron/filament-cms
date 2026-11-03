<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;
use Workbench\App\Cms\ShopSettings;

class DynamicValuesTest extends TestCase
{
    private function saveSettings(array $changes = []): void
    {
        app(ContentStore::class)->save('settings', array_replace_recursive([
            'business' => ['name' => 'Kedai Adam', 'legal_name' => 'Adam Enterprise', 'registration_number' => '202301012345', 'logo' => 'cms/logo.png'],
            'contact' => ['phones' => ['012-345 6789', '03-1234 5678'], 'email' => 'kedai@example.com', 'address' => 'No. 1, Jalan Satu', 'map_url' => 'https://maps.google.com/?q=adam'],
            'hours' => ['days' => [['days' => 'Isnin - Jumaat', 'time' => '9 pagi - 6 petang'], ['days' => 'Sabtu', 'time' => '9 pagi - 1 petang']], 'note' => 'Tutup cuti umum'],
            'social' => ['links' => [['platform' => 'facebook', 'url' => 'https://facebook.com/kedai'], ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@kedai']]],
        ], $changes), null);
    }

    private function value(string $key): ?string
    {
        return app(DynamicValues::class)->resolve($key, new RenderContext);
    }

    public function test_every_site_value_reads_the_settings(): void
    {
        $this->saveSettings();

        $this->assertSame('Kedai Adam', $this->value('site.name'));
        $this->assertSame('Adam Enterprise', $this->value('site.legal_name'));
        $this->assertSame('202301012345', $this->value('site.registration'));
        $this->assertSame('012-345 6789', $this->value('site.phone'));
        $this->assertSame('012-345 6789 / 03-1234 5678', $this->value('site.phones'));
        $this->assertSame('kedai@example.com', $this->value('site.email'));
        $this->assertSame('No. 1, Jalan Satu', $this->value('site.address'));
        $this->assertSame("Isnin - Jumaat: 9 pagi - 6 petang\nSabtu: 9 pagi - 1 petang", $this->value('site.hours'));
        $this->assertSame('Tutup cuti umum', $this->value('site.hours_note'));
        $this->assertSame('https://wa.me/60123456789', $this->value('site.whatsapp'));
        $this->assertSame('60123456789', $this->value('site.whatsapp_number'));
        $this->assertSame('tel:+60123456789', $this->value('site.phone_link'));
        $this->assertSame('mailto:kedai@example.com', $this->value('site.email_link'));
        $this->assertSame('https://maps.google.com/?q=adam', $this->value('site.map'));
        $this->assertSame('https://facebook.com/kedai', $this->value('site.facebook'));
        $this->assertSame('https://tiktok.com/@kedai', $this->value('site.tiktok'));
        $this->assertNull($this->value('site.instagram'));
        $this->assertSame('/storage/cms/logo.png', $this->value('site.logo'));
    }

    public function test_the_builder_gets_each_value_with_its_type(): void
    {
        $types = array_column(app(DynamicValues::class)->definitions(), 'type', 'key');

        $this->assertSame('text', $types['site.phone']);
        $this->assertSame('link', $types['site.whatsapp']);
        $this->assertSame('link', $types['site.facebook']);
        $this->assertSame('image', $types['site.logo']);
        $this->assertSame('text', $types['page.title']);
        $this->assertSame('link', $types['page.url']);
    }

    public function test_a_change_to_the_settings_shows_at_once(): void
    {
        $this->saveSettings();
        $this->assertSame('012-345 6789', $this->value('site.phone'));

        $this->saveSettings(['contact' => ['phones' => ['019-999 0000']]]);

        $this->assertSame('019-999 0000', $this->value('site.phone'));
        $this->assertSame('https://wa.me/60199990000', $this->value('site.whatsapp'));
    }

    public function test_a_project_that_extends_the_standard_settings_keeps_its_defaults(): void
    {
        config(['filament-cms.settings' => ShopSettings::class]);

        $this->assertSame('Kedai Contoh', $this->value('site.name'));
        $this->assertSame('011-1111 2222', $this->value('site.phone'));
    }

    public function test_a_page_draws_site_and_page_values(): void
    {
        $this->saveSettings();
        $words = Blocks::paragraph('');
        $words['content'] = [Blocks::dynamic('site.phone'), ['type' => 'text', 'text' => ' · ', 'styles' => []], Blocks::dynamic('page.title')];
        SitePage::query()->create([
            'title' => 'Hubungi kami',
            'path' => '/hubungi',
            'is_published' => true,
            'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [$words, Blocks::button('WhatsApp', '@site.whatsapp')]]]],
        ]);

        $this->get('/hubungi')
            ->assertOk()
            ->assertSee('012-345 6789 · Hubungi kami')
            ->assertSee('href="https://wa.me/60123456789"', false);
    }
}
