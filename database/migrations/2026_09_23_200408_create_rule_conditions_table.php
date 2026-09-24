<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('configurator_rules')->cascadeOnDelete();
            $table->unsignedBigInteger('condition_group_id')->nullable();
            $table->foreign(['condition_group_id', 'rule_id'], 'condition_group_owner_fk')->references(['id', 'rule_id'])->on('rule_condition_groups')->cascadeOnDelete();
            $table->string('source_kind', 30);
            $table->foreignId('source_configurator_attribute_id')->nullable()->constrained('configurator_attributes')->restrictOnDelete();
            $table->string('property_key', 100)->nullable();
            $table->string('context_dimension', 20)->nullable();
            $table->string('operator', 20);
            $table->json('operand')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rule_conditions');
    }
};
