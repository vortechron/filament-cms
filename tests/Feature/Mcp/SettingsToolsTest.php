<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Illuminate\Support\Facades\Storage;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Mcp\Tools\GetSettings;
use Vortechron\FilamentCms\Mcp\Tools\SaveSettings;
use Vortechron\FilamentCms\Models\CmsRevision;
use Workbench\App\Cms\ShopSettings;
use Workbench\App\Models\User;

class SettingsToolsTest extends McpTestCase
{
    private function saveSettings(): void
    {
        app(ContentStore::class)->save('settings', [
            'business' => ['name' => 'Kedai Adam', 'logo' => 'cms/logo.png', 'extra' => 'kekal'],
            'contact' => ['phones' => ['012-345 6789'], 'email' => 'kedai@example.com'],
            'seo' => ['title' => 'Tajuk SEO'],
        ], null);
    }

    public function test_get_settings_shows_each_section_with_its_values_over_the_defaults(): void
    {
        config(['filament-cms.settings' => ShopSettings::class]);

        $sections = array_column($this->runTool(GetSettings::class)['sections'], null, 'key');

        $this->assertSame('Kedai Contoh', $sections['business']['values']['name']);
        $this->assertSame(['011-1111 2222'], $sections['contact']['values']['phones']);
        $this->assertTrue($sections['contact']['editable']);
        $this->assertSame('list', array_column($sections['contact']['fields'], null, 'name')['phones']['type']);
        $this->assertFalse($sections['seo']['editable']);
        $this->assertFalse($sections['seo']['values']['allow_indexing']);
    }

    public function test_save_settings_merges_each_section_and_keeps_a_version_under_the_mcp_user(): void
    {
        $agent = User::factory()->create(['email' => 'ai@vortechron.com']);
        config(['filament-cms.mcp.user' => 'ai@vortechron.com']);
        $this->saveSettings();

        $result = $this->runTool(SaveSettings::class, ['settings' => [
            'contact' => ['email' => 'baru@example.com', 'phones' => ['019-999 0000', '03-1111 2222']],
            'hours' => ['days' => [['days' => 'Isnin - Jumaat', 'time' => '9 - 6']], 'note' => 'Tutup Ahad'],
        ]]);

        $this->assertSaved($result);
        $settings = cms_settings();
        $this->assertSame('baru@example.com', $settings->section('contact')->email);
        $this->assertSame(['019-999 0000', '03-1111 2222'], $settings->section('contact')->phones);
        $this->assertSame('Tutup Ahad', $settings->section('hours')->note);
        $this->assertSame('Kedai Adam', $settings->section('business')->name);
        $this->assertSame('kekal', $settings->section('business')->extra);
        $this->assertSame('Tajuk SEO', $settings->section('seo')->title);
        $this->assertSame($agent->getKey(), CmsRevision::query()->where('key', 'settings')->latest('id')->value('user_id'));
        $this->assertSame('baru@example.com', array_column($result['sections'], null, 'key')['contact']['values']['email']);
    }

    public function test_save_settings_refuses_bad_values_and_unknown_keys_and_changes_nothing(): void
    {
        $this->saveSettings();
        $before = app(Cms::class)->saved('settings');

        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['contact' => ['email' => 'bukan emel', 'map_url' => 'javascript:alert(1)']]]), 'contact.email', 'contact.map_url');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['business' => ['name' => '']]]), 'business.name');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['contact' => ['fax' => '03-1234']]]), 'contact.fax');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['delivery' => ['area' => 'Johor']]]), 'delivery');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['seo' => ['title' => 'Baru']]]), 'seo');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['contact' => 'x']]), 'contact');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => []]), 'settings');

        $this->assertSame($before, app(Cms::class)->saved('settings'));
        $this->assertSame(1, CmsRevision::query()->where('key', 'settings')->count());
    }

    public function test_a_new_logo_is_a_picture_uploaded_to_the_site_given_by_path_or_address(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms/baru.png', 'png');
        Storage::disk('public')->put('cms/lain.png', 'png');
        $this->saveSettings();

        $this->assertSaved($this->runTool(SaveSettings::class, ['settings' => ['business' => ['logo' => 'cms/logo.png', 'tagline' => 'Sejak 1990']]]));
        $this->assertSame('cms/logo.png', cms_settings()->section('business')->logo);

        $this->assertSaved($this->runTool(SaveSettings::class, ['settings' => ['business' => ['logo' => 'cms/baru.png']]]));
        $this->assertSame('cms/baru.png', cms_settings()->section('business')->logo);

        $this->assertSaved($this->runTool(SaveSettings::class, ['settings' => ['business' => ['logo' => Storage::disk('public')->url('cms/lain.png')]]]));
        $this->assertSame('cms/lain.png', cms_settings()->section('business')->logo);

        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['business' => ['logo' => 'https://evil.example/logo.png']]]), 'business.logo');
        $this->assertRefused($this->runTool(SaveSettings::class, ['settings' => ['business' => ['logo' => 'cms/../private/x.png']]]), 'business.logo');
        $this->assertSame('cms/lain.png', cms_settings()->section('business')->logo);
    }
}
