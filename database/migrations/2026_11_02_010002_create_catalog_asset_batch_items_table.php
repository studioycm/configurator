<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_asset_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('batch_id')->constrained('catalog_asset_batches')->restrictOnDelete();
            $table->unsignedBigInteger('record_id');
            $table->unsignedInteger('position');
            $table->char('record_fingerprint', 64)->nullable();
            $table->foreignId('before_asset_id')->nullable()->constrained('catalog_assets')->restrictOnDelete();
            $table->json('snapshot');
            $table->boolean('included')->default(true);
            $table->string('match_status', 20);
            $table->string('error_code', 64)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'record_id'], 'catalog_review_items_record_unique');
            $table->unique(['batch_id', 'position'], 'catalog_review_items_position_unique');
            $table->index(['batch_id', 'match_status', 'included', 'position', 'id'], 'catalog_review_items_state_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_asset_batch_items');
    }
};
