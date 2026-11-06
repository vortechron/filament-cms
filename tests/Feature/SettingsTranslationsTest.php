<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsSettingsTranslations;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SiteInfo;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\TestCase;

class SettingsTranslationsTest extends TestCase
{
    public function test_only_configured_text_settings_change_with_language_and_other_facts_stay_shared(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-cms.translatable_settings' => ['hours' => ['note']]]);
        app(ContentStore::class)->save('settings', ['contact' => ['phones' => ['0123456789']], 'hours' => ['note' => 'Tutup Ahad']], null);
        app(CmsSettingsTranslations::class)->save('en', ['hours' => ['note' => 'Closed on Sunday']]);

        $english = SiteLanguages::run('en', fn () => app(Cms::class)->saved('settings'));
        $malay = SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'));
        $this->assertSame('Closed on Sunday', $english['hours']['note']);
        $this->assertSame('Tutup Ahad', $malay['hours']['note']);
        $this->assertSame(['0123456789'], $english['contact']['phones']);
    }

    public function test_an_english_note_save_does_not_rewrite_the_default_settings(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-cms.translatable_settings' => ['hours' => ['note']]]);
        app(ContentStore::class)->save('settings', ['hours' => ['note' => 'Tutup Ahad']], null);
        $before = CmsRevision::where('key', 'settings')->count();

        SiteLanguages::run('en', fn () => app(SiteInfo::class)->save(['hours' => ['note' => 'Closed Sunday']], null));

        $this->assertSame($before, CmsRevision::where('key', 'settings')->count());
        $this->assertSame('Tutup Ahad', SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'))['hours']['note']);
    }

    public function test_an_old_english_builder_keeps_newer_shared_facts_when_only_its_note_changes(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-cms.translatable_settings' => ['hours' => ['note']]]);
        app(ContentStore::class)->save('settings', ['hours' => ['note' => 'Nota asal'], 'contact' => ['phones' => ['0123456789']]], null);
        $page = SitePage::create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $english->getKey()]);
        $values = $builder->instance()->getPageBuilderOptions()['siteInfo']['values'];
        app(ContentStore::class)->save('settings', ['hours' => ['note' => 'Nota terkini'], 'contact' => ['phones' => ['0199999999']]], null);
        $values['hours']['note'] = 'English note';

        $builder->call('saveSiteInfoFromPageBuilder', $values)->assertSuccessful();
        $values['hours']['note'] = 'English note again';
        $builder->call('saveSiteInfoFromPageBuilder', $values)->assertSuccessful();

        $saved = SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'));
        $this->assertSame('Nota terkini', $saved['hours']['note']);
        $this->assertSame(['0199999999'], $saved['contact']['phones']);
        $this->assertSame('English note again', CmsSettingsTranslations::saved('en')['hours']['note']);
    }
}
