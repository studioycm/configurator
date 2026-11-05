<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('title');
            $table->string('locale', 10)->default('en');
            $table->boolean('enabled')->default(false);
            $table->json('steps');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['key', 'locale']);
            $table->index(['enabled', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_definitions');
    }
};
