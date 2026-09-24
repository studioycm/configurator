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

        Schema::create('configuration_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_configuration_id')->constrained();
            $table->string('spec_group')->nullable();
            $table->string('key');
            $table->string('value');
            $table->string('unit')->nullable();
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuration_specifications');
    }
};
