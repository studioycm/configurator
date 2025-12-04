<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('file_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_group_id')->nullable()->constrained();
            $table->foreignId('product_profile_id')->nullable()->constrained();
            $table->foreignId('product_configuration_id')->nullable()->constrained();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('sort_order')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_attachments');
    }
};
