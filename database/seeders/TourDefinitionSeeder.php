<?php

namespace Database\Seeders;

use App\Models\TourDefinition;
use App\Services\GuidedTours;
use Illuminate\Database\Seeder;

class TourDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GuidedTours::starters() as $starter) {
            TourDefinition::query()->firstOrCreate(['key' => $starter['key'], 'locale' => $starter['locale']], $starter);
        }
    }
}
