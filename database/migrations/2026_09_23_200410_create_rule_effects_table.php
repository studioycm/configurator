<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_effects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('configurator_rules')->cascadeOnDelete();
            $table->foreignId('target_configurator_attribute_id')->constrained('configurator_attributes')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('target_scope', 20);
            $table->string('display_value')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rule_effects');
    }
};
