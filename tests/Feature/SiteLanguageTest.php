<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\Mail\FormSubmissionMail;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

/**
 * Our clients run an English panel (APP_LOCALE=en) for a Malay site
 * (SITE_LOCALE=ms): visitors read Malay, the owner reads English.
 */
class SiteLanguageTest extends TestCase
{
    use MakesForms;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        config(['filament-block-editor.content_locale' => 'ms']);
    }

    public function test_visitors_read_the_page_and_the_form_errors_in_the_site_language(): void
    {
        $form = self::exampleForm();
        $form['submit']['text'] = '';
        $page = $this->pageWithForm($form);

        $this->get('/hubungi')->assertOk()->assertSee('Hantar');
        $this->send($page, self::goodInput(['nama' => '']));

        $this->assertSame('Nama perlu diisi.', session('errors')->first('form.frm00001.nama'));
        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_shop_email_is_in_the_panel_language(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        app(ContentStore::class)->save('settings', ['contact' => ['phones' => ['012-345 6789'], 'email' => 'kedai@example.com']], null);
        $page = $this->pageWithForm(self::exampleForm([['type' => 'email', 'to' => '@site.email', 'subject' => '']]));

        $this->send($page, self::goodInput());

        Mail::assertSent(FormSubmissionMail::class, function (FormSubmissionMail $mail): bool {
            $this->assertTrue($mail->hasSubject('New form: Borang pertanyaan'));
            $this->assertStringContainsString('Someone sent the', $mail->render());

            return true;
        });
    }

    public function test_the_panel_is_in_english(): void
    {
        config(['filament-cms.max_pages' => 5]);
        SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $this->actingAsClient();

        Livewire::test(ManageSitePages::class)->assertSee('1 of 5 pages used.');
    }

    public function test_the_entry_list_is_named_in_english_and_its_empty_words_are_malay(): void
    {
        app(EntryTypes::class)->register(EntryType::make('product')->label('Product')->fields([Field::text('price')]));
        $block = app(BlockRegistry::class)->get('cms-entries')?->toArray();
        $fields = collect($block['fields'] ?? [])->keyBy('name');

        $this->assertSame('Entry list', $block['label'] ?? null);
        $this->assertSame('Belum ada entri.', $fields['empty_text']['default'] ?? null);

        app(EntryTypes::class)->forget('product');
    }
}
