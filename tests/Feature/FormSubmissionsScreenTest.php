<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormSubmissionsScreenTest extends TestCase
{
    use MakesForms;

    /**
     * @param  array<string, mixed>  $data
     */
    private function submission(string $form, string $status = FormSubmission::NEW, array $data = ['nama' => 'Ali'], int $daysAgo = 0): FormSubmission
    {
        $submission = FormSubmission::query()->create(['form_key' => 'sec00001.frm00001', 'form_name' => $form, 'data' => $data, 'status' => $status, 'meta' => ['page' => '/hubungi']]);
        $submission->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $submission;
    }

    public function test_the_list_is_newest_first_and_filters_by_form_and_status(): void
    {
        $old = $this->submission('Borang pertanyaan', FormSubmission::READ, daysAgo: 2);
        $order = $this->submission('Tempahan', daysAgo: 1);
        $spam = $this->submission('Borang pertanyaan', FormSubmission::SPAM);
        $this->actingAsClient();

        $this->get(FormSubmissionResource::getUrl())->assertOk()->assertSee(['Borang', 'Baru', 'Dibaca', 'Spam']);

        Livewire::test(ListFormSubmissions::class)
            ->assertCanSeeTableRecords([$spam, $order, $old], inOrder: true)
            ->filterTable('form_name', 'Tempahan')
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$old, $spam])
            ->resetTableFilters()
            ->filterTable('status', FormSubmission::SPAM)
            ->assertCanSeeTableRecords([$spam])
            ->assertCanNotSeeTableRecords([$old, $order]);
    }

    public function test_the_menu_badge_counts_new_submissions(): void
    {
        $this->assertNull(FormSubmissionResource::getNavigationBadge());

        $this->submission('A');
        $this->submission('B');
        $this->submission('C', FormSubmission::READ);

        $this->assertSame('2', FormSubmissionResource::getNavigationBadge());
    }

    public function test_the_view_shows_each_label_and_value_escaped_and_marks_it_read(): void
    {
        $page = $this->pageWithForm();
        $this->send($page, self::goodInput(['nama' => '<script>alert(1)</script>']));
        $submission = FormSubmission::query()->sole();
        $this->actingAsClient();

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSeeInOrder(['Nama', '&lt;script&gt;alert(1)&lt;/script&gt;', 'Telefon', '012-345 6789', 'Servis', 'Parcel', 'Hari', 'Isnin', 'Saya setuju dihubungi.', 'Ya'], false)
            ->assertDontSee('<script>alert(1)', false);

        $this->assertSame(FormSubmission::READ, $submission->fresh()->status);
    }

    public function test_without_the_stored_form_the_labels_are_the_field_names(): void
    {
        $submission = $this->submission('Lama', data: ['nombor_ic' => '900101-01-1234']);
        $this->actingAsClient();

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))->assertOk()->assertSeeInOrder(['nombor_ic', '900101-01-1234']);
    }

    public function test_a_submission_can_be_marked_spam_or_new_and_deleted(): void
    {
        $first = $this->submission('A', FormSubmission::READ);
        $second = $this->submission('B');
        $third = $this->submission('C');
        $this->actingAsClient();

        Livewire::test(ListFormSubmissions::class)
            ->callAction(TestAction::make('spam')->table($first))
            ->assertActionHidden(TestAction::make('spam')->table($first->fresh()));
        $this->assertSame(FormSubmission::SPAM, $first->fresh()->status);

        Livewire::test(ViewFormSubmission::class, ['record' => $first->getKey()])->callAction('new');
        $this->assertSame(FormSubmission::NEW, $first->fresh()->status);

        Livewire::test(ListFormSubmissions::class)->callAction(TestAction::make('delete')->table($first));
        $this->assertModelMissing($first);

        Livewire::test(ListFormSubmissions::class)->callTableBulkAction('delete', [$second, $third]);
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_there_is_no_create_or_edit(): void
    {
        $this->actingAsClient();

        $this->assertFalse(FormSubmissionResource::canCreate());
        $this->assertFalse(FormSubmissionResource::canEdit($this->submission('A')));
        $this->assertSame(['index', 'view'], array_keys(FormSubmissionResource::getPages()));
    }

    public function test_csv_export_streams_the_filtered_rows_safe_for_spreadsheets(): void
    {
        $page = $this->pageWithForm();
        $this->send($page, self::goodInput(['nama' => '=HYPERLINK("https://evil.example")', 'mesej' => "Dua\nbaris, dengan \"petik\""]));
        $this->submission('Tempahan', FormSubmission::SPAM, ['nama' => 'Spam']);
        $this->actingAsClient();

        $component = Livewire::test(ListFormSubmissions::class)
            ->filterTable('status', FormSubmission::NEW)
            ->callAction('export')
            ->assertFileDownloaded('borang-'.now()->format('Y-m-d').'.csv');

        $csv = base64_decode(data_get($component->effects, 'download.content'));
        $rows = array_map(str_getcsv(...), explode("\r\n", trim(substr($csv, 3))));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(['Diterima', 'Borang', 'Status', 'Halaman', 'Nama', 'Telefon', 'Servis', 'Mesej', 'Hari', 'Saya setuju dihubungi.'], $rows[0]);
        $this->assertCount(1, array_slice($rows, 1));
        $this->assertSame(['Borang pertanyaan', 'Baru', '/hubungi', '\'=HYPERLINK("https://evil.example")', '012-345 6789', 'Parcel', "Dua\nbaris, dengan \"petik\"", 'Isnin', 'Ya'], array_slice($rows[1], 1));
    }

    public function test_only_people_who_may_use_the_panel_see_submissions(): void
    {
        $submission = $this->submission('A');

        $this->get(FormSubmissionResource::getUrl())->assertRedirect();
        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))->assertRedirect();

        Gate::policy(FormSubmission::class, DenySubmissionsPolicy::class);
        $this->actingAsClient();
        $this->get(FormSubmissionResource::getUrl())->assertForbidden();
    }

    public function test_the_screen_can_be_switched_off(): void
    {
        $on = Panel::make()->id('on');
        FilamentCmsPlugin::make()->register($on);
        $off = Panel::make()->id('off');
        FilamentCmsPlugin::make()->forms(false)->register($off);

        $this->assertContains(FormSubmissionResource::class, $on->getResources());
        $this->assertNotContains(FormSubmissionResource::class, $off->getResources());
    }
}

class DenySubmissionsPolicy
{
    public function viewAny(): bool
    {
        return false;
    }
}
