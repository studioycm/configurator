<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_asset_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('target_type', 40);
            $table->string('role', 40);
            $table->string('operation', 20);
            $table->json('owner_context');
            $table->json('recipe');
            $table->char('creation_hash', 64);
            $table->foreignUlid('source_id')->constrained('catalog_asset_import_sources')->restrictOnDelete();
            $table->unsignedInteger('source_version');
            $table->string('scope_path', 1024);
            $table->char('scope_sha256', 64);
            $table->unsignedInteger('scope_count');
            $table->string('manifest_path', 1024);
            $table->char('manifest_sha256', 64);
            $table->string('status', 20)->default('building');
            $table->unsignedInteger('build_position')->default(0);
            $table->unsignedInteger('build_revision')->default(0);
            $table->unsignedInteger('preview_version')->default(1);
            $table->ulid('build_token')->nullable();
            $table->timestamp('build_expires_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_asset_batches');
    }
};
