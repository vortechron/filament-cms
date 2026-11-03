<?php

namespace Vortechron\FilamentCms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A form submission in the shape the forms track's contract gives
 * `cms_form_submissions`, for testing the MCP tools without that track.
 */
class FormSubmissionFixture extends Model
{
    protected $table = 'cms_form_submissions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'meta' => 'array'];
    }

    public static function createTable(): void
    {
        if (Schema::hasTable('cms_form_submissions')) {
            return;
        }

        Schema::create('cms_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->nullable()->index();
            $table->string('form_key', 100)->index();
            $table->string('form_name')->nullable();
            $table->json('data');
            $table->json('meta')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();
        });
    }
}
