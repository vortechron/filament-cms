<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('cms_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->index();
            $table->json('content');
            $table->foreignId('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_revisions');
        Schema::dropIfExists('cms_contents');
    }
};
