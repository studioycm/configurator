<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configurator_id')->constrained('configurators')->restrictOnDelete();
            $table->string('label');
            $table->string('kind', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('driver_configurator_attribute_id')->nullable()->constrained('configurator_attributes')->restrictOnDelete();
            $table->foreignId('target_configurator_attribute_id')->nullable()->constrained('configurator_attributes')->restrictOnDelete();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_rules');
    }
};
