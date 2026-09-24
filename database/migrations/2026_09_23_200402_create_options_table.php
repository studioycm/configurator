<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->foreignId('value_id')->constrained('values')->restrictOnDelete();
            /** Preserve a third trailing character so MySQL CHECK can reject it before VARCHAR truncation. */
            $code = $table->string('code', 3);
            if (DB::getDriverName() === 'mysql') {
                $code->collation('utf8mb4_0900_bin');
            }
            $table->unique('code');
            $table->unique(['attribute_id', 'value_id']);
            $table->timestamps();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE options ADD CONSTRAINT options_code_ascii CHECK (CHAR_LENGTH(code) = 2 AND REGEXP_LIKE(code COLLATE utf8mb4_0900_bin, _utf8mb4'^[A-Za-z0-9]{2}$' COLLATE utf8mb4_0900_bin, 'c'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
