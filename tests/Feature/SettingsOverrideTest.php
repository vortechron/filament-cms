<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Event;
use RuntimeException;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Events\SettingsOverridden;
use Vortechron\FilamentCms\SiteInfo;
use Vortechron\FilamentCms\Tests\TestCase;

class SettingsOverrideTest extends TestCase
{
    private const STORED = [
        'business' => ['name' => 'Kedai Adam'],
        'contact' => ['phones' => ['012-345 6789']],
    ];

    private const PREVIEW = [
        'business' => ['name' => 'Kedai Baru'],
        'contact' => ['phones' => ['019-999 0000']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(ContentStore::class)->save('settings', self::STORED, null);
    }

    private function phone(): ?string
    {
        return app(DynamicValues::class)->resolve('site.phone', new RenderContext);
    }

    public function test_every_reader_sees_the_settings_while_drawing_and_the_stored_ones_after(): void
    {
        $seen = app(Cms::class)->withSettings(self::PREVIEW, fn (): array => [
            cms_settings()->section('business')->name,
            $this->phone(),
            app(SiteInfo::class)->forBuilder()['values']['contact']['phones'],
        ]);

        $this->assertSame(['Kedai Baru', '019-999 0000', [['number' => '019-999 0000']]], $seen);
        $this->assertSame('Kedai Adam', cms_settings()->section('business')->name);
        $this->assertSame('012-345 6789', $this->phone());
        $this->assertSame(self::STORED, app(Cms::class)->saved(Cms::SETTINGS));
    }

    public function test_the_stored_settings_come_back_when_drawing_throws(): void
    {
        try {
            app(Cms::class)->withSettings(self::PREVIEW, fn () => throw new RuntimeException('Lukisan gagal'));
            $this->fail('The exception was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Lukisan gagal', $exception->getMessage());
        }

        $this->assertSame('Kedai Adam', cms_settings()->section('business')->name);
        $this->assertSame('012-345 6789', $this->phone());
    }

    public function test_a_host_hears_when_the_settings_change_and_when_they_come_back(): void
    {
        $heard = [];
        Event::listen(SettingsOverridden::class, function (SettingsOverridden $event) use (&$heard): void {
            $heard[] = [$event->settings['business']['name'] ?? null, cms_settings()->section('business')->name];
        });

        app(Cms::class)->withSettings(self::PREVIEW, fn (): string => 'drawn');

        $this->assertSame([['Kedai Baru', 'Kedai Baru'], [null, 'Kedai Adam']], $heard);
    }
}
