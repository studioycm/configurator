<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configurator_attribute_id')->constrained('configurator_attributes')->restrictOnDelete();
            $table->foreignId('option_id')->constrained('options')->restrictOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('label_override')->nullable();
            $table->string('display_value_override')->nullable();
            $table->text('hint')->nullable();
            $table->boolean('hidden_by_default')->default(false);
            $table->boolean('disabled_by_default')->default(false);
            $table->unique(['configurator_attribute_id', 'option_id'], 'co_canonical_unique');
            $table->unique(['configurator_attribute_id', 'id'], 'co_membership_unique');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_options');
    }
};
