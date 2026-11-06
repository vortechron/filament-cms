<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->json('text_overrides')->nullable();
            $table->unsignedSmallInteger('shared_format')->nullable();
            $table->unsignedInteger('edit_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cms_documents', function (Blueprint $table): void {
            $table->dropColumn(['text_overrides', 'shared_format', 'edit_version']);
        });
    }
};
