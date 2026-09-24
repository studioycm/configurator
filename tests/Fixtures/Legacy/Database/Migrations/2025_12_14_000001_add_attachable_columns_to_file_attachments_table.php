<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_attachments', function (Blueprint $table): void {
            $missingType = ! Schema::hasColumn('file_attachments', 'attachable_type');
            $missingId = ! Schema::hasColumn('file_attachments', 'attachable_id');

            if ($missingType) {
                $table->string('attachable_type')->nullable()->after('id');
            }

            if ($missingId) {
                $table->unsignedBigInteger('attachable_id')->nullable()->after('attachable_type');
            }

            if ($missingType && $missingId) {
                $table->index(['attachable_type', 'attachable_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('file_attachments', function (Blueprint $table): void {
            // For SQLite in tests, dropping indexed columns can error; only drop if both exist
            if (Schema::hasColumn('file_attachments', 'attachable_type') && Schema::hasColumn('file_attachments', 'attachable_id')) {
                $table->dropIndex('file_attachments_attachable_type_attachable_id_index');
                $table->dropColumn('attachable_id');
                $table->dropColumn('attachable_type');
            }
        });
    }
};
