<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['group_filters', 'sub_groups', 'rule_conditions'] as $table) {
            $this->changeCollation($table, 'utf8mb4_0900_bin');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $collations = [];
        foreach (['group_filters', 'sub_groups', 'rule_conditions'] as $table) {
            $collation = DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)->value('table_collation');
            if (! is_string($collation) || preg_match('/\Autf8mb4_[a-z0-9_]+\z/D', $collation) !== 1) {
                throw new RuntimeException('Cannot restore the product field reference collation for '.$table.'.');
            }
            $collations[$table] = $collation;
        }
        $collision = DB::table('group_filters')->select('group_id')->groupBy('group_id')
            ->groupByRaw('property_key COLLATE '.$collations['group_filters'])->havingRaw('COUNT(*) > 1')->exists();
        if ($collision) {
            throw new RuntimeException('Cannot roll back product field references: colliding group filter keys under the original table collation.');
        }

        foreach ($collations as $table => $collation) {
            $this->changeCollation($table, $collation);
        }
    }

    private function changeCollation(string $table, string $collation): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $collation): void {
            $blueprint->string('property_key', 100)->charset('utf8mb4')->collation($collation)->nullable($table === 'rule_conditions')->change();
        });
    }
};
