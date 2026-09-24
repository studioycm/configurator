<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurators', function (Blueprint $table): void {
            $table->json('context_schema')->nullable();
            $table->json('policy_overrides')->nullable();
        });

    }

    public function down(): void
    {
        Schema::table('configurators', function (Blueprint $table): void {
            $table->dropColumn(['context_schema', 'policy_overrides']);
        });
    }
};
