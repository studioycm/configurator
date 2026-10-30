<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_tag_value', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->foreignId('group_tag_value_id')->constrained('group_tag_values')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->unique(['group_id', 'group_tag_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_tag_value');
    }
};
