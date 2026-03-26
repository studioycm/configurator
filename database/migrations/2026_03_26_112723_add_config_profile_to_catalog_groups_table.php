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
        Schema::table('catalog_groups', function (Blueprint $table) {
            $missingId = ! Schema::hasColumn('catalog_groups', 'config_profile_id');

            if ($missingId) {
                $table->foreignId('config_profile_id')->nullable()->after('parent_id');
                $table->index(['config_profile_id']);
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_groups', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_groups', 'config_profile_id')) {
                $table->dropIndex('catalog_groups_config_profile_id_index');
                $table->dropColumn('config_profile_id');
            }
        });
    }
};
