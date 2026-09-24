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
        Schema::table('option_rules', function (Blueprint $table) {
            $table->json('rule_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_rules', function (Blueprint $table) {
            $table->dropColumn(['rule_payload', 'is_active', 'priority']);
        });
    }
};
