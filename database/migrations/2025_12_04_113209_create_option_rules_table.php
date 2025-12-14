<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('option_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('config_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_attribute_id')->constrained('config_attributes')->cascadeOnDelete();
            $table->json('allowed_option_ids')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_rules');
    }
};
