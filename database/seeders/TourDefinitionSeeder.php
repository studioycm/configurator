<?php

namespace Database\Seeders;

use App\Models\TourDefinition;
use App\Services\GuidedTours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TourDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GuidedTours::starters() as $starter) {
            DB::transaction(function () use ($starter): void {
                $tour = TourDefinition::query()->firstOrCreate(['key' => $starter['key'], 'locale' => $starter['locale']], $starter);
                if ($starter['key'] !== 'screen-writing' || $tour->wasRecentlyCreated) {
                    return;
                }
                $tour = TourDefinition::query()->lockForUpdate()->findOrFail($tour->id);
                $originalSteps = $starter['steps'];
                $originalSteps[1]['description'] = 'A draft is not an applied rule. Save keeps the draft; use the explicit review and Apply flow to change saved Rules. This tour never saves or applies a draft.';
                if ($tour->revision === 1 && $tour->enabled && $tour->title === $starter['title']
                    && $tour->created_at->equalTo($tour->updated_at) && $tour->steps == $originalSteps) {
                    $tour->forceFill(['steps' => $starter['steps'], 'revision' => 2])->save();
                }
            });
        }
    }
}
