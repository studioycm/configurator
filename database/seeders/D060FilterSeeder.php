<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class D060FilterSeeder extends Seeder
{
    /** Statically transcribed conf.js values; size entities decoded once to canonical input. */
    public const array FILTERS = [
        ['Working_Pressure', 'Working Pressure', ['6 bar (90 psi)', '10 bar (150 psi)', '16 bar (250 psi)', '25 bar (360 psi)', '40 bar (580 psi)']],
        ['Valve_Type', 'Valve Type', ['Standard Flow', 'One Way - Out', 'Non Slam']],
        ['Connection_Type', 'Connection Type', ['Threaded', 'Flange']],
        ['Connection_Size', 'Connection Size', ['1″', '2″', '3″', '4″', '6″', '8″', '10″']],
        ['Automatic_Type', 'Automatic Type', ['Composite', 'Metal Shell', 'Metal']],
        ['Automatic_Config', 'Automatic Config', ['Standard', 'Extended']],
        ['Discharge_Outlet_Type', 'Discharge Outlet Type', ['Screen Cover', 'One Way Outlet', 'Vent Pipe Connection']],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $group = Group::where('legacy_id', '2144')->lockForUpdate()->first();
            if ($group === null) {
                return;
            }
            foreach (self::FILTERS as $order => [$key, $label, $values]) {
                $group->filters()->firstOrCreate(['property_key' => $key], ['label' => $label, 'value_order' => $values, 'value_labels' => [], 'sort_order' => $order]);
            }
        });
    }
}
