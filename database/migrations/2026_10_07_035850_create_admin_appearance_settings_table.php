<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_appearance_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('settings');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        DB::table('admin_appearance_settings')->insert(['id' => 1, 'settings' => json_encode(['cell_padding_block' => 6, 'cell_padding_inline' => 10, 'workspace_gap' => 8, 'header_height' => 48, 'logo_height' => 28, 'sidebar_width' => 216, 'collapsed_sidebar_width' => 58, 'navigation_padding_block' => 6, 'control_height' => 32, 'modal_width' => 'medium', 'slide_over_width' => 'medium'], JSON_THROW_ON_ERROR), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_appearance_settings');
    }
};
