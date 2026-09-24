<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('option_rules', function (Blueprint $table) {
            $table->string('dependency_type')->nullable()->after('allowed_option_ids');
        });

        DB::table('option_rules')
            ->whereNull('dependency_type')
            ->update([
                'dependency_type' => DB::raw("coalesce(json_extract(rule_payload, '$.ui_mode'), 'disabled')"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_rules', function (Blueprint $table) {
            $table->dropColumn('dependency_type');
        });
    }
};
