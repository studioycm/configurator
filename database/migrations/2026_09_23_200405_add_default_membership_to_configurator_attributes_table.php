<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_attributes', function (Blueprint $table): void {
            $table->foreign(['id', 'default_configurator_option_id'], 'ca_default_membership_fk')->references(['configurator_attribute_id', 'id'])->on('configurator_options')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('configurator_attributes', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['id', 'default_configurator_option_id'] : 'ca_default_membership_fk');
        });
    }
};
