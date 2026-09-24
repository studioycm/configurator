<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_effect_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('effect_id')->constrained('rule_effects')->cascadeOnDelete();
            $table->foreignId('configurator_option_id')->constrained('configurator_options')->restrictOnDelete();
            $table->unique(['effect_id', 'configurator_option_id'], 'effect_option_unique');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rule_effect_options');
    }
};
