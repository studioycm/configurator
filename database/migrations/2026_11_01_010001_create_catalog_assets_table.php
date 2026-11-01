<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_kind', 20);
            $table->json('source');
            $table->char('sha256', 64);
            $table->char('import_key', 64)->nullable()->unique();
            $table->string('preparation_status', 12)->default('preparing');
            $table->ulid('preparation_token')->nullable();
            $table->timestamp('preparation_expires_at')->nullable();
            $table->foreignId('original_media_id')->nullable()->unique()->constrained('media')->restrictOnDelete();
            $table->json('verified_metadata')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_assets');
    }
};
