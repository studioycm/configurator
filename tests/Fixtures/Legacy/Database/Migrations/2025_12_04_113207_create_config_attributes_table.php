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

        Schema::create('config_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('slug')->nullable();
            $table->string('input_type')->default('toggle');
            $table->integer('sort_order')->index();
            $table->boolean('is_required')->default(true);
            $table->integer('segment_index')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_attributes');
    }
};
