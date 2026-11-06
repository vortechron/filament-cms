<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_documents', function (Blueprint $table): void {
            // Null is the existing site's default language. No content is rewritten.
            $table->string('locale', 20)->nullable()->index();
            $table->foreignId('translation_of')->nullable()->constrained('cms_documents')->cascadeOnDelete();
            $table->unique(['translation_of', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->dropUnique(['translation_of', 'locale']);
            $table->dropForeign(['translation_of']);
            $table->dropColumn(['locale', 'translation_of']);
        });
    }
};
