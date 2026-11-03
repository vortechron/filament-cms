<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentCms\Mcp\Contracts\McpFormSubmissions;
use Vortechron\FilamentCms\Mcp\Forms\EloquentFormSubmissions;
use Vortechron\FilamentCms\Mcp\Forms\NoFormSubmissions;
use Vortechron\FilamentCms\Mcp\Tools\ListFormSubmissions;
use Vortechron\FilamentCms\Mcp\Tools\UpdateFormSubmission;
use Vortechron\FilamentCms\Tests\Fixtures\FormSubmissionFixture;

class FormSubmissionToolsTest extends McpTestCase
{
    private function useFixture(): void
    {
        FormSubmissionFixture::createTable();
        $this->app->instance(McpFormSubmissions::class, new EloquentFormSubmissions(FormSubmissionFixture::class));
    }

    private function submission(string $status = 'new', string $form = 'sec1.form1', array $data = ['nama' => 'Ali']): FormSubmissionFixture
    {
        return FormSubmissionFixture::query()->create([
            'document_id' => 1, 'form_key' => $form, 'form_name' => 'Pertanyaan', 'data' => $data, 'status' => $status,
            'meta' => ['page' => '/hubungi', 'ip_hash' => 'abc123', 'user_agent' => 'Safari'],
        ]);
    }

    public function test_without_the_forms_track_the_tools_say_forms_are_not_set_up(): void
    {
        $this->app->instance(McpFormSubmissions::class, new NoFormSubmissions);

        $this->assertRefused($this->runTool(ListFormSubmissions::class), 'forms');
        $this->assertRefused($this->runTool(UpdateFormSubmission::class, ['id' => 1, 'status' => 'read']), 'forms');
    }

    public function test_it_lists_submissions_newest_first_with_counts_and_filters(): void
    {
        $this->useFixture();
        $first = $this->submission('read');
        $second = $this->submission('new', data: ['nama' => 'Siti']);
        $this->submission('spam', 'sec2.form9');

        $all = $this->runTool(ListFormSubmissions::class);
        $new = $this->runTool(ListFormSubmissions::class, ['status' => 'new']);
        $form = $this->runTool(ListFormSubmissions::class, ['form_key' => 'sec1.form1', 'limit' => 1]);

        $this->assertSame(['new' => 1, 'read' => 1, 'spam' => 1], $all['counts']);
        $this->assertCount(3, $all['submissions']);
        $this->assertSame([$second->getKey()], array_column($new['submissions'], 'id'));
        $this->assertSame(['nama' => 'Siti'], $new['submissions'][0]['data']);
        $this->assertSame('/hubungi', $new['submissions'][0]['meta']['page']);
        $this->assertArrayNotHasKey('ip_hash', $new['submissions'][0]['meta']);
        $this->assertSame([$second->getKey()], array_column($form['submissions'], 'id'));
        $this->assertSame([$first->getKey()], array_column($this->runTool(ListFormSubmissions::class, ['form_key' => 'sec1.form1', 'before_id' => $second->getKey()])['submissions'], 'id'));
        $this->assertRefused($this->runTool(ListFormSubmissions::class, ['status' => 'deleted']), 'status');
    }

    public function test_it_changes_the_status_of_a_submission(): void
    {
        $this->useFixture();
        $submission = $this->submission();

        $this->assertSaved($this->runTool(UpdateFormSubmission::class, ['id' => $submission->getKey(), 'status' => 'spam']));
        $this->assertSame('spam', $submission->fresh()->status);

        $this->assertRefused($this->runTool(UpdateFormSubmission::class, ['id' => $submission->getKey(), 'status' => 'gone']), 'status');
        $this->assertRefused($this->runTool(UpdateFormSubmission::class, ['id' => 999, 'status' => 'read']), 'id');
        $this->assertSame('spam', $submission->fresh()->status);
    }
}
