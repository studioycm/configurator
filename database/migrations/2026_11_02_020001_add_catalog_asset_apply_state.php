<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_asset_batches', function (Blueprint $table): void {
            $table->json('pending_apply_intent')->nullable();
            $table->ulid('active_acceptance_id')->nullable()->index('catalog_apply_active_acceptance_idx');
        });
        Schema::table('catalog_asset_batch_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('prepared_asset_id')->nullable();
            $table->foreign('prepared_asset_id', 'catalog_apply_prepared_asset_fk')->references('id')->on('catalog_assets')->restrictOnDelete();
            $table->ulid('acceptance_id')->nullable();
            $table->unsignedInteger('accepted_preview_version')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->ulid('request_step_id')->nullable()->unique('catalog_apply_step_unique');
            $table->ulid('processing_token')->nullable();
            $table->timestamp('processing_expires_at')->nullable();
            $table->string('apply_status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->char('execution_fingerprint', 64)->nullable();
            $table->index(['batch_id', 'acceptance_id', 'apply_status', 'position', 'id'], 'catalog_apply_items_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_asset_batch_items', function (Blueprint $table): void {
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['prepared_asset_id'] : 'catalog_apply_prepared_asset_fk');
            $table->dropUnique('catalog_apply_step_unique');
            $table->dropIndex('catalog_apply_items_order_idx');
            $table->dropColumn(['prepared_asset_id', 'acceptance_id', 'accepted_preview_version', 'accepted_at', 'request_step_id', 'processing_token', 'processing_expires_at', 'apply_status', 'attempts', 'applied_at', 'execution_fingerprint']);
        });
        Schema::table('catalog_asset_batches', function (Blueprint $table): void {
            $table->dropIndex('catalog_apply_active_acceptance_idx');
            $table->dropColumn(['pending_apply_intent', 'active_acceptance_id']);
        });
    }
};
