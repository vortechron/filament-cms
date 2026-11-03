<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vortechron\FilamentBlockEditor\Content\PageValue;

/**
 * Site pages become one kind of document (page format 2). The table keeps its
 * ids, so revision keys "page:{id}" still find their versions. Each page's
 * sections are rewritten as a format 2 page value. Plain queries only: the
 * models will change after this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('cms_pages', 'cms_documents');

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->string('kind', 20)->default('page')->index();
            $table->string('entry_type', 60)->nullable()->index();
            $table->unsignedInteger('sort')->default(0);
            $table->json('settings')->nullable();
        });

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->renameColumn('sections', 'content');
        });

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->string('path')->nullable()->change();
        });

        $this->rewrite(fn (mixed $content): array => PageValue::normalize($content));
    }

    public function down(): void
    {
        DB::table('cms_documents')->where('kind', '!=', 'page')->delete();

        $this->rewrite(fn (mixed $content): array => PageValue::normalize($content)['sections']);

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->string('path')->nullable(false)->change();
        });

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->renameColumn('content', 'sections');
        });

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropIndex(['entry_type']);
        });

        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'entry_type', 'sort', 'settings']);
        });

        Schema::rename('cms_documents', 'cms_pages');
    }

    /**
     * @param  Closure(mixed): array<mixed>  $change
     */
    private function rewrite(Closure $change): void
    {
        DB::table('cms_documents')->orderBy('id')->lazyById(100)->each(function (object $row) use ($change): void {
            $content = is_string($row->content) ? json_decode($row->content, true) : $row->content;

            DB::table('cms_documents')->where('id', $row->id)->update(['content' => json_encode($change($content))]);
        });
    }
};
