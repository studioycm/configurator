<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_options', function (Blueprint $table): void {
            $table->string('subtitle_mode', 16)->default('none');
        });
        DB::table('configurator_options')->orderBy('id')->chunkById(500, function (Collection $rows): void {
            foreach ($rows as $row) {
                $blank = $row->display_value_override === null || trim($row->display_value_override) === '';
                DB::table('configurator_options')->where('id', $row->id)->update([
                    'subtitle_mode' => $blank ? 'none' : 'custom',
                    'display_value_override' => $blank ? null : $row->display_value_override,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurator_options', function (Blueprint $table): void {
            $table->dropColumn('subtitle_mode');
        });
    }
};
