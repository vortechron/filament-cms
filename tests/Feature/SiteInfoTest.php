<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Tests\TestCase;
use Workbench\App\Cms\ShopSettings;

class SiteInfoTest extends TestCase
{
    private function builder(): Testable
    {
        $this->actingAsClient();

        return Livewire::test(BuildSitePage::class, ['record' => Document::findOrCreatePart(Document::HEADER)->getKey()]);
    }

    private function saveSettings(): void
    {
        app(ContentStore::class)->save('settings', [
            'business' => ['name' => 'Kedai Adam', 'logo' => 'cms/logo.png', 'extra' => 'kekal'],
            'contact' => ['phones' => ['012-345 6789'], 'email' => 'kedai@example.com'],
            'hours' => ['days' => [['days' => 'Isnin', 'time' => '9 - 5']]],
            'seo' => ['title' => 'Tajuk SEO'],
        ], null);
    }

    /**
     * @return array<string, mixed>
     */
    private function save(Testable $builder, array $values): array
    {
        return $builder->call('saveSiteInfoFromPageBuilder', $values)->effects['returns'][0];
    }

    public function test_the_builder_gets_the_shop_info_groups_and_their_values(): void
    {
        $this->saveSettings();

        $siteInfo = $this->builder()->instance()->getPageBuilderOptions()['siteInfo'];

        $this->assertSame(['business', 'contact', 'hours', 'social'], array_column($siteInfo['groups'], 'key'));
        $this->assertSame('Perniagaan', $siteInfo['groups'][0]['label']);
        $contact = array_column($siteInfo['groups'][1]['fields'], null, 'name');
        $this->assertSame('list', $contact['phones']['type']);
        $this->assertSame(['number'], array_column($contact['phones']['fields'], 'name'));
        $this->assertSame([['number' => '012-345 6789']], $siteInfo['values']['contact']['phones']);
        $this->assertSame('/storage/cms/logo.png', $siteInfo['values']['business']['logo']);
        $this->assertSame([['days' => 'Isnin', 'time' => '9 - 5']], $siteInfo['values']['hours']['days']);
        $this->assertArrayNotHasKey('extra', $siteInfo['values']['business']);
        $this->assertArrayNotHasKey('seo', $siteInfo['values']);
    }

    public function test_saving_the_shop_info_updates_the_settings_and_keeps_what_the_builder_does_not_show(): void
    {
        $this->saveSettings();
        $builder = $this->builder();

        $result = $this->save($builder, [
            'contact' => ['phones' => [['number' => '019-999 0000'], ['number' => '03-1111 2222']], 'email' => 'baru@example.com'],
            'hours' => ['days' => [['days' => 'Sabtu', 'time' => '10 - 2']], 'note' => 'Tutup Ahad'],
            'social' => ['links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/adam']]],
            'seo' => ['title' => 'Diubah'],
        ]);

        $this->assertTrue($result['ok']);
        // The answer carries the shop values read again, so the builder's rows show the new phone at once.
        $this->assertSame('019-999 0000', collect($result['dynamicValues'])->firstWhere('key', 'site.phone')['preview']);
        $settings = cms_settings();
        $this->assertSame(['019-999 0000', '03-1111 2222'], $settings->section('contact')->phones);
        $this->assertSame('baru@example.com', $settings->section('contact')->email);
        $this->assertSame([['days' => 'Sabtu', 'time' => '10 - 2']], $settings->section('hours')->days);
        $this->assertSame('instagram', $settings->section('social')->links[0]['platform']);
        $this->assertSame('Kedai Adam', $settings->section('business')->name);
        $this->assertSame('cms/logo.png', $settings->section('business')->logo);
        $this->assertSame('kekal', $settings->section('business')->extra);
        $this->assertSame('Tajuk SEO', $settings->section('seo')->title);
        $this->assertSame(2, CmsRevision::query()->where('key', 'settings')->count());
        $this->assertSame('019-999 0000', app(DynamicValues::class)->resolve('site.phone', new RenderContext));
    }

    public function test_the_shop_info_is_checked_like_the_tetapan_screen(): void
    {
        $this->saveSettings();
        $builder = $this->builder();

        $result = $this->save($builder, [
            'business' => ['name' => ''],
            'contact' => ['email' => 'bukan emel', 'map_url' => 'javascript:alert(1)', 'phones' => array_fill(0, 5, ['number' => '012'])],
            'social' => ['links' => [['platform' => 'myspace', 'url' => 'ftp://example.com']]],
        ]);

        $this->assertFalse($result['ok']);
        foreach (['business.name', 'contact.email', 'contact.map_url', 'contact.phones', 'social.links.0.platform', 'social.links.0.url'] as $key) {
            $this->assertArrayHasKey($key, $result['errors'], $key);
            $this->assertIsString($result['errors'][$key]);
        }
        $this->assertSame('Kedai Adam', cms_settings()->section('business')->name);
        $this->assertSame(1, CmsRevision::query()->where('key', 'settings')->count());
    }

    public function test_a_new_logo_must_be_a_picture_uploaded_here(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('block-editor/baru.png', 'png');
        $this->saveSettings();
        $builder = $this->builder();

        $refused = $this->save($builder, ['business' => ['logo' => 'https://evil.example/logo.png']]);
        $this->assertArrayHasKey('business.logo', $refused['errors']);

        $this->assertTrue($this->save($builder, ['business' => ['logo' => Storage::disk('public')->url('block-editor/baru.png')]])['ok']);
        $this->assertSame('block-editor/baru.png', cms_settings()->section('business')->logo);

        $this->assertTrue($this->save($builder, ['business' => ['logo' => null]])['ok']);
        $this->assertNull(cms_settings()->section('business')->logo);
    }

    public function test_a_new_logo_must_be_an_image_in_an_upload_folder(): void
    {
        Storage::fake('public');
        foreach (['cms/kedai.WEBP', 'block-editor/logo.svg', 'block-editor/nota.pdf', 'block-editor/sub/x.png', 'private/rahsia.png', 'cms/.png', 'block-editor/x.png'] as $file) {
            Storage::disk('public')->put($file, 'x');
        }
        $this->saveSettings();
        $builder = $this->builder();
        $url = fn (string $path): string => '/storage/'.$path;

        foreach ([
            'private/rahsia.png',
            'block-editor/logo.svg',
            'block-editor/nota.pdf',
            'block-editor/sub/x.png',
            'cms/.png',
            'block-editor/%2e%2e/private/rahsia.png',
            'block-editor/./x.png',
            'block-editor//x.png',
            'block-editor/x.png?v=1',
            'block-editor%2Fx.png',
        ] as $path) {
            $result = $this->save($builder, ['business' => ['logo' => $url($path)]]);
            $this->assertFalse($result['ok'], $path);
            $this->assertSame(__('filament-cms::settings.picture_here'), $result['errors']['business.logo'], $path);
        }

        $this->assertSame('cms/logo.png', cms_settings()->section('business')->logo);
        $this->assertTrue($this->save($builder, ['business' => ['logo' => $url('cms/kedai.WEBP')]])['ok']);
        $this->assertSame('cms/kedai.WEBP', cms_settings()->section('business')->logo);
    }

    public function test_the_builder_folder_counts_only_when_the_builder_uploads_to_the_cms_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('block-editor/x.png', 'x');
        config(['filament-block-editor.disk' => 's3']);
        $this->saveSettings();

        $result = $this->save($this->builder(), ['business' => ['logo' => '/storage/block-editor/x.png']]);

        $this->assertFalse($result['ok']);
    }

    public function test_a_project_that_extends_the_standard_settings_keeps_working(): void
    {
        config(['filament-cms.settings' => ShopSettings::class]);

        $siteInfo = $this->builder()->instance()->getPageBuilderOptions()['siteInfo'];

        $this->assertSame('Kedai Contoh', $siteInfo['values']['business']['name']);
        $this->assertSame([['number' => '011-1111 2222']], $siteInfo['values']['contact']['phones']);
    }

    public function test_with_parts_switched_off_there_is_no_shop_info_in_the_builder(): void
    {
        $builder = $this->builder();
        FilamentCmsPlugin::get()->parts(false);

        $this->assertArrayNotHasKey('siteInfo', $builder->instance()->getPageBuilderOptions());
        $this->assertSame([], $builder->instance()->getPageBuilderOptions()['parts'] ?? []);
        $this->assertFalse($builder->instance()->getPageBuilderSupports()['saveSiteInfo']);
        $this->assertFalse($this->save($builder, ['business' => ['name' => 'X']])['ok']);
    }
}
