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

        Schema::create('configuration_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('part_number');
            $table->string('label')->nullable();
            $table->string('material')->nullable();
            $table->decimal('quantity', 8, 3)->nullable();
            $table->string('unit')->nullable();
            $table->integer('segment_index')->nullable();
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('configuration_parts');
    }
};
