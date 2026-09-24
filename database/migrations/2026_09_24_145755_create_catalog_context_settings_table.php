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
        Schema::create('catalog_context_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('choices');
            $table->timestamps();
        });

        DB::table('catalog_context_settings')->insert([
            'id' => 1,
            'choices' => json_encode([
                'territory' => array_map(fn (string $value): array => ['value' => $value, 'label' => $value], ['USA', 'Germany', 'Europe', 'Russia', 'Australia']),
                'application' => array_map(fn (string $value): array => ['value' => $value, 'label' => $value], ['Industry', 'Water Supply', 'Agriculture', 'Wastewater']),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_context_settings');
    }
};
