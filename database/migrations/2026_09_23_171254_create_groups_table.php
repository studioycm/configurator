<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $legacy = $table->string('legacy_id', 64)->nullable()->unique();
            if (DB::getDriverName() === 'mysql') {
                $legacy->collation('utf8mb4_0900_bin');
            }
            $table->foreignId('parent_id')->nullable()->constrained('groups')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('configurator_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('result_settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
