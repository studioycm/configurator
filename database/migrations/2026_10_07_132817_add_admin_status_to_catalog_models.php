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
        foreach (['attributes', 'options', 'configurators', 'products', 'groups', 'configurator_attributes'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });
        }
        Schema::table('options', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false);
        });
        Schema::table('configurators', function (Blueprint $table): void {
            $table->string('disabled_group_behavior', 20)->default('visible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configurators', fn (Blueprint $table) => $table->dropColumn('disabled_group_behavior'));
        Schema::table('options', fn (Blueprint $table) => $table->dropColumn('is_hidden'));
        foreach (['attributes', 'options', 'configurators', 'products', 'groups', 'configurator_attributes'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_active'));
        }
    }
};
