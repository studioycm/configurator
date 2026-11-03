<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_rule_writing_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('configurator_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->longText('source');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('format_version')->default(1);
            $table->unsignedBigInteger('revision')->default(1);
            $table->unsignedBigInteger('origin_rule_id')->nullable();
            $table->json('base_rule')->nullable();
            $table->json('bindings')->nullable();
            $table->json('last_saved_authoring')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['configurator_id', 'origin_rule_id'], 'writing_draft_owner_origin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_rule_writing_drafts');
    }
};
