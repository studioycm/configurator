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

        Schema::create('product_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_profile_id')->constrained();
            $table->string('configuration_code')->index();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('drawing_image_path')->nullable();
            $table->json('config_data')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_configurations');
    }
};
