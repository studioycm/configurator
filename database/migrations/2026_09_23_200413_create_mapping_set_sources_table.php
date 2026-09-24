<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_set_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mapping_set_id');
            $table->unsignedBigInteger('rule_id');
            $table->foreign(['mapping_set_id', 'rule_id'], 'mapping_source_owner_fk')->references(['id', 'rule_id'])->on('mapping_sets')->cascadeOnDelete();
            $table->foreignId('configurator_option_id')->constrained('configurator_options')->restrictOnDelete();
            $table->unique(['rule_id', 'configurator_option_id'], 'mapping_source_unique');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_set_sources');
    }
};
