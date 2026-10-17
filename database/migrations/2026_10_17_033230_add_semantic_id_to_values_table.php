<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('values', function (Blueprint $table) {
            $table->string('semantic_id', 100)->nullable()->unique();
        });

        DB::table('values')->orderBy('id')->chunkById(500, function ($values): void {
            foreach ($values as $value) {
                $base = rtrim(substr(Str::slug($value->label, '_'), 0, 100), '_') ?: 'value';
                $semanticId = $base;
                $suffix = 2;

                while (DB::table('values')->where('semantic_id', $semanticId)->exists()) {
                    $ending = '_'.$suffix++;
                    $semanticId = rtrim(substr($base, 0, 100 - strlen($ending)), '_').$ending;
                }

                DB::table('values')->where('id', $value->id)->update(['semantic_id' => $semanticId]);
            }
        });

        Schema::table('values', function (Blueprint $table) {
            $table->string('semantic_id', 100)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('values', function (Blueprint $table) {
            $table->dropUnique(['semantic_id']);
            $table->dropColumn('semantic_id');
        });
    }
};
