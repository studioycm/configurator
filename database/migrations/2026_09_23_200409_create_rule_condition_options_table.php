<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_condition_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condition_id')->constrained('rule_conditions')->cascadeOnDelete();
            $table->foreignId('configurator_option_id')->constrained('configurator_options')->restrictOnDelete();
            $table->unique(['condition_id', 'configurator_option_id'], 'condition_option_unique');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rule_condition_options');
    }
};
