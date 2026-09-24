<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_filters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('property_key', 100);
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->json('value_order');
            $table->json('value_labels');
            $table->unique(['group_id', 'property_key']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_filters');
    }
};
