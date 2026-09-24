<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $legacy = $table->string('legacy_id', 64)->unique();
            $legacyGroup = $table->string('legacy_group_id', 64);
            $code = $table->string('product_code', 64);
            if (DB::getDriverName() === 'mysql') {
                $legacy->collation('utf8mb4_0900_bin');
                $legacyGroup->collation('utf8mb4_0900_bin');
                $code->collation('utf8mb4_0900_bin');
            }
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->text('description');
            $table->json('properties');
            $table->json('parts');
            $table->json('extra_data');
            $table->index(['group_id', 'product_code', 'id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
