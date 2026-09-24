<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_set_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mapping_set_id')->constrained('mapping_sets')->cascadeOnDelete();
            $table->foreignId('configurator_option_id')->constrained('configurator_options')->restrictOnDelete();
            $table->unique(['mapping_set_id', 'configurator_option_id'], 'mapping_target_unique');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_set_targets');
    }
};
