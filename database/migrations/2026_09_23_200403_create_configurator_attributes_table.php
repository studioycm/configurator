<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configurator_id')->constrained('configurators')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('code_order')->default(0);
            $table->string('label_override')->nullable();
            $table->string('input_type', 20)->default('toggle');
            $table->text('help_text')->nullable();
            $table->unsignedBigInteger('default_configurator_option_id')->nullable();
            $table->unique(['configurator_id', 'attribute_id'], 'ca_canonical_unique');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_attributes');
    }
};
