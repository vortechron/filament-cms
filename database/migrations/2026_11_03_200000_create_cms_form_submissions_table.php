<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->string('form_key', 100)->index();
            $table->string('form_name')->nullable();
            $table->json('data');
            $table->json('meta')->nullable();
            $table->string('status', 10)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_form_submissions');
    }
};
