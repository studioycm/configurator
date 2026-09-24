<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('configurator_rules')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['id', 'rule_id']);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_sets');
    }
};
