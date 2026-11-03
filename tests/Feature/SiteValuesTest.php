<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Tests\TestCase;
use Workbench\App\Cms\ClinicSettings;

class SiteValuesTest extends TestCase
{
    /**
     * Every registered value in order: key, label, type, group, settings key.
     * Stored pages and the builder rely on all of it.
     */
    private const REGISTERED = [
        ['site.name', 'Nama perniagaan', 'text', 'Perniagaan', 'business'],
        ['site.legal_name', 'Nama syarikat berdaftar', 'text', 'Perniagaan', 'business'],
        ['site.registration', 'No. pendaftaran (SSM)', 'text', 'Perniagaan', 'business'],
        ['site.logo', 'Logo', 'image', 'Perniagaan', 'business'],
        ['site.phone', 'Telefon (nombor pertama)', 'text', 'Hubungi', 'contact'],
        ['site.phones', 'Semua nombor telefon', 'text', 'Hubungi', 'contact'],
        ['site.email', 'Emel', 'text', 'Hubungi', 'contact'],
        ['site.address', 'Alamat', 'text', 'Hubungi', 'contact'],
        ['site.whatsapp_number', 'Nombor WhatsApp', 'text', 'Hubungi', 'contact'],
        ['site.whatsapp', 'Pautan WhatsApp', 'link', 'Hubungi', 'contact'],
        ['site.phone_link', 'Pautan panggilan', 'link', 'Hubungi', 'contact'],
        ['site.email_link', 'Pautan emel', 'link', 'Hubungi', 'contact'],
        ['site.map', 'Pautan Google Maps', 'link', 'Hubungi', 'contact'],
        ['site.hours', 'Waktu operasi', 'text', 'Waktu operasi', 'hours'],
        ['site.hours_note', 'Nota waktu operasi', 'text', 'Waktu operasi', 'hours'],
        ['site.facebook', 'Facebook', 'link', 'Media sosial', 'social'],
        ['site.instagram', 'Instagram', 'link', 'Media sosial', 'social'],
        ['site.tiktok', 'TikTok', 'link', 'Media sosial', 'social'],
        ['site.threads', 'Threads', 'link', 'Media sosial', 'social'],
        ['site.x', 'X', 'link', 'Media sosial', 'social'],
        ['site.youtube', 'YouTube', 'link', 'Media sosial', 'social'],
        ['site.shopee', 'Shopee', 'link', 'Media sosial', 'social'],
        ['site.lazada', 'Lazada', 'link', 'Media sosial', 'social'],
        ['page.title', 'Tajuk halaman', 'text', 'Halaman ini', null],
        ['page.url', 'Alamat halaman', 'link', 'Halaman ini', null],
    ];

    public function test_the_registered_values_are_exactly_these_in_this_order(): void
    {
        $seen = array_map(
            fn (array $definition): array => [$definition['key'], $definition['label'], $definition['type'], $definition['group'], $definition['settings'] ?? null],
            app(DynamicValues::class)->definitions(),
        );

        $this->assertSame(self::REGISTERED, $seen);
    }

    public function test_the_standard_site_values_register_when_no_settings_page_is_set(): void
    {
        config(['filament-cms.settings' => null]);
        app()->forgetInstance(DynamicValues::class);

        $keys = array_column(app(DynamicValues::class)->definitions(), 'key');

        $this->assertSame(array_column(self::REGISTERED, 0), $keys);
    }

    public function test_a_section_of_the_project_adds_its_own_short_codes(): void
    {
        config(['filament-cms.settings' => ClinicSettings::class]);
        app()->forgetInstance(DynamicValues::class);
        app(ContentStore::class)->save('settings', ['clinic' => ['doctor' => 'Dr. Aina']], null);

        $definition = collect(app(DynamicValues::class)->definitions())->firstWhere('key', 'site.doctor_on_duty');

        $this->assertSame(['site.doctor_on_duty', 'Doktor bertugas', 'text', 'Klinik', 'clinic'], [$definition['key'], $definition['label'], $definition['type'], $definition['group'], $definition['settings']]);
        $this->assertSame('Dr. Aina', app(DynamicValues::class)->resolve('site.doctor_on_duty', new RenderContext));
        $this->assertContains('site.phone', array_column(app(DynamicValues::class)->definitions(), 'key'));
    }

    public function test_the_builder_preview_changes_what_a_moved_value_shows(): void
    {
        app(ContentStore::class)->save('settings', ['contact' => ['phones' => ['012-345 6789']]], null);
        $phone = fn (): ?string => app(DynamicValues::class)->resolve('site.phone', new RenderContext);

        $preview = app(Cms::class)->withSettings(['contact' => ['phones' => ['019-999 0000']]], $phone);

        $this->assertSame('019-999 0000', $preview);
        $this->assertSame('012-345 6789', $phone());
    }
}
