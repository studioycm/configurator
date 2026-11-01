<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_asset_import_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_kind', 20);
            $table->json('source');
            $table->unsignedInteger('scan_version')->default(1);
            $table->unsignedInteger('scan_revision')->default(0);
            $table->string('status', 20)->default('uploading');
            $table->json('cursor');
            $table->json('upload_ledger');
            $table->boolean('traversal_complete')->default(false);
            $table->boolean('uploads_sealed')->default(false);
            $table->unsignedInteger('unsettled_uploads')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('byte_count')->default(0);
            $table->unsignedInteger('visited_nodes')->default(0);
            $table->ulid('scan_token')->nullable();
            $table->timestamp('scan_expires_at')->nullable();
            $table->string('manifest_path', 1024)->nullable();
            $table->char('manifest_sha256', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_asset_import_sources');
    }
};
