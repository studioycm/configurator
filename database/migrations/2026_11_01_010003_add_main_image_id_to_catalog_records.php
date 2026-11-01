<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'groups'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('main_image_id')->nullable()->index()->constrained('catalog_assets')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'groups'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['main_image_id']);
                $table->dropIndex(['main_image_id']);
                $table->dropColumn('main_image_id');
            });
        }
    }
};
