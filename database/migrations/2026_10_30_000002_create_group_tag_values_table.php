<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_tag_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_tag_category_id')->constrained('group_tag_categories')->restrictOnDelete();
            $table->string('label', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['group_tag_category_id', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_tag_values');
    }
};
