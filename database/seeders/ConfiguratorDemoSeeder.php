<?php

namespace Database\Seeders;

use App\FileAttachmentType;
use App\Models\CatalogGroup;
use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\ConfigProfile;
use App\Models\ConfigurationPart;
use App\Models\ConfigurationSpecification;
use App\Models\FileAttachment;
use App\Models\OptionRule;
use App\Models\Part;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use Illuminate\Database\Seeder;

class ConfiguratorDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Catalog group (general category, not the "D-060 Series" header)
        $group = CatalogGroup::firstOrCreate(
            ['slug' => 'combination-air-valves'],
            [
                'name' => 'Combination Air Valves',
                'description' => 'Combination air valves product family',
                'is_active' => true,
            ],
        );

        // 2) Product profile: D60S
        $profile = ProductProfile::firstOrCreate(
            ['product_code' => 'D60S-P16-03'],
            [
                'catalog_group_id' => $group->id,
                'name' => 'Combination Air Valve, D-060 Series',
                'short_label' => 'D60S',
                'is_active' => true,
            ],
        );

        // 3) Config profile (configurator) for this product profile
        $configProfile = ConfigProfile::firstOrCreate(
            [
                'product_profile_id' => $profile->id,
                'slug' => 'd60s-p16-03-configurator',
            ],
            [
                'name' => 'D60S Configuration',
                'description' => 'Configurator for D60S-P16-03',
                'scope' => 'configuration_selection',
                'is_active' => true,
            ],
        );

        // Helper to keep sort & segment indexes aligned
        $order = 0;
        $nextOrder = function () use (&$order) {
            $order++;

            return $order;
        };

        // 4) Attributes (stages) – in exact order

        // 1. Flange Standard
        $flange = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'flange-standard',
            ],
            [
                'name' => 'flange_standard',
                'label' => 'Flange Standard',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 2. Kinetic Valve Body Material
        $kvBody = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'kinetic-valve-body-material',
            ],
            [
                'name' => 'kinetic_valve_body_material',
                'label' => 'Kinetic Valve Body Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 3. Kinetic Valve Seal Material
        $kvSeal = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'kinetic-valve-seal-material',
            ],
            [
                'name' => 'kinetic_valve_seal_material',
                'label' => 'Kinetic Valve Seal Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 4. Kinetic Valve Seat Material
        $kvSeat = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'kinetic-valve-seat-material',
            ],
            [
                'name' => 'kinetic_valve_seat_material',
                'label' => 'Kinetic Valve Seat Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 5. Kinetic Valve Bolt Set Material
        $kvBolt = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'kinetic-valve-bolt-set-material',
            ],
            [
                'name' => 'kinetic_valve_bolt_set_material',
                'label' => 'Kinetic Valve Bolt Set Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 6. Kinetic Valve Float Material
        $kvFloat = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'kinetic-valve-float-material',
            ],
            [
                'name' => 'kinetic_valve_float_material',
                'label' => 'Kinetic Valve Float Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 7. Automatic Valve Body Material
        $avBody = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'automatic-valve-body-material',
            ],
            [
                'name' => 'automatic_valve_body_material',
                'label' => 'Automatic Valve Body Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 8. Automatic Valve Seal Material
        $avSeal = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'automatic-valve-seal-material',
            ],
            [
                'name' => 'automatic_valve_seal_material',
                'label' => 'Automatic Valve Seal Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 9. Automatic Valve Float Material
        $avFloat = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'automatic-valve-float-material',
            ],
            [
                'name' => 'automatic_valve_float_material',
                'label' => 'Automatic Valve Float Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 10. O-ring Material
        $oring = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'o-ring-material',
            ],
            [
                'name' => 'o_ring_material',
                'label' => 'O-ring Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 11. Air Release Outlet
        $airRelease = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'air-release-outlet',
            ],
            [
                'name' => 'air_release_outlet',
                'label' => 'Air Release Outlet',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 12. Pressure Release Outlet
        $pressureRelease = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'pressure-release-outlet',
            ],
            [
                'name' => 'pressure_release_outlet',
                'label' => 'Pressure Release Outlet',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 13. Screen Cover Material
        $screenCover = ConfigAttribute::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'slug' => 'screen-cover-material',
            ],
            [
                'name' => 'screen_cover_material',
                'label' => 'Screen Cover Material',
                'input_type' => 'toggle',
                'sort_order' => $nextOrder(),
                'segment_index' => $order,
                'is_required' => true,
            ],
        );

        // 5) Options with codes & defaults
        // We match this default code:
        // A1-CS-VT-BR-S5-DX-S7-V2-PP-B2-PN-BV-P2

        // Flange Standard
        $flangeAsa150 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $flange->id,
                'code' => 'A1',
            ],
            [
                'label' => 'ASA 150',
                'sort_order' => 1,
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $flangeDin16 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $flange->id,
                'code' => 'D6',
            ],
            [
                'label' => 'DIN 16',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Kinetic Valve Body Material
        $kvBodyDi = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvBody->id,
                'code' => 'DI',
            ],
            [
                'label' => 'Ductile Iron',
                'sort_order' => 1,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvBodyCs = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvBody->id,
                'code' => 'CS',
            ],
            [
                'label' => 'Cast Steel A216',
                'sort_order' => 2,
                'is_default' => true,  // default = CS
                'is_active' => true,
            ],
        );

        $kvBodyS6 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvBody->id,
                'code' => 'S6',
            ],
            [
                'label' => 'St.St. 316',
                'sort_order' => 3,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvBodyDx = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvBody->id,
                'code' => 'DX',
            ],
            [
                'label' => 'Duplex 5A',
                'sort_order' => 4,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Kinetic Valve Seal Material
        $kvSealEp = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeal->id,
                'code' => 'EP',
            ],
            [
                'label' => 'EPDM',
                'sort_order' => 1,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvSealVt = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeal->id,
                'code' => 'VT',
            ],
            [
                'label' => 'Viton',
                'sort_order' => 2,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        $kvSealBn = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeal->id,
                'code' => 'BN',
            ],
            [
                'label' => 'Buna-N',
                'sort_order' => 3,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Kinetic Valve Seat Material
        $kvSeatBr = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeat->id,
                'code' => 'BR',
            ],
            [
                'label' => 'Bronze',
                'sort_order' => 1,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        $kvSeatS3 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeat->id,
                'code' => 'S3',
            ],
            [
                'label' => 'St.St. 316',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvSeatD5 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeat->id,
                'code' => 'D5',
            ],
            [
                'label' => 'Duplex 5A',
                'sort_order' => 3,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvSeatAb = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvSeat->id,
                'code' => 'AB',
            ],
            [
                'label' => 'Aluminum Bronze',
                'sort_order' => 4,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Kinetic Valve Bolt Set Material
        $kvBoltS5 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvBolt->id,
                'code' => 'S5',
            ],
            [
                'label' => 'Steel',
                'sort_order' => 1,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        $kvBoltS6 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvBolt->id,
                'code' => 'S6',
            ],
            [
                'label' => 'St.St. 316',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Kinetic Valve Float Material
        $kvFloatPc = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvFloat->id,
                'code' => 'PC',
            ],
            [
                'label' => 'Polycarbonate',
                'sort_order' => 1,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvFloatS7 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvFloat->id,
                'code' => 'S7',
            ],
            [
                'label' => 'St.St. 316',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $kvFloatDx = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $kvFloat->id,
                'code' => 'DX',
            ],
            [
                'label' => 'Duplex',
                'sort_order' => 3,
                'is_default' => true, // default DX
                'is_active' => true,
            ],
        );

        // Automatic Valve Body Material
        $avBodyS7 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $avBody->id,
                'code' => 'S7',
            ],
            [
                'label' => 'St.St. 316',
                'sort_order' => 1,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        $avBodyD5 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $avBody->id,
                'code' => 'D5',
            ],
            [
                'label' => 'Duplex 5A',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Automatic Valve Seal Material
        $avSealEp = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $avSeal->id,
                'code' => 'EP',
            ],
            [
                'label' => 'EPDM',
                'sort_order' => 1,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $avSealV2 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $avSeal->id,
                'code' => 'V2',
            ],
            [
                'label' => 'Viton',
                'sort_order' => 2,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        // Automatic Valve Float Material
        $avFloatPp = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $avFloat->id,
                'code' => 'PP',
            ],
            [
                'label' => 'Polypropylene',
                'sort_order' => 1,
                'is_default' => true, // only option, default
                'is_active' => true,
            ],
        );

        // O-ring Material
        $oringB2 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $oring->id,
                'code' => 'B2',
            ],
            [
                'label' => 'Buna-N',
                'sort_order' => 1,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        $oringE2 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $oring->id,
                'code' => 'E2',
            ],
            [
                'label' => 'EPDM',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $oringV3 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $oring->id,
                'code' => 'V3',
            ],
            [
                'label' => 'Viton',
                'sort_order' => 3,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // Air Release Outlet
        $airPb = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $airRelease->id,
                'code' => 'PB',
            ],
            [
                'label' => 'Polypropylene, BSPT',
                'sort_order' => 1,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $airPn = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $airRelease->id,
                'code' => 'PN',
            ],
            [
                'label' => 'Polypropylene, NPT',
                'sort_order' => 2,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        // Pressure Release Outlet
        $prWithout = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $pressureRelease->id,
                'code' => 'W0',
            ],
            [
                'label' => 'Without',
                'sort_order' => 1,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $prSsPlug = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $pressureRelease->id,
                'code' => 'SP',
            ],
            [
                'label' => 'St.St. Plug',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $prBrassPlug = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $pressureRelease->id,
                'code' => 'BP',
            ],
            [
                'label' => 'Brass Plug',
                'sort_order' => 3,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $prSsBall = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $pressureRelease->id,
                'code' => 'SV',
            ],
            [
                'label' => 'St.St. Ball Valve',
                'sort_order' => 4,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $prBrassBall = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $pressureRelease->id,
                'code' => 'BV',
            ],
            [
                'label' => 'Brass Ball Valve',
                'sort_order' => 5,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        // Screen Cover Material
        $screenP2 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $screenCover->id,
                'code' => 'P2',
            ],
            [
                'label' => 'Polypropylene',
                'sort_order' => 1,
                'is_default' => true, // default
                'is_active' => true,
            ],
        );

        $screenDi2 = ConfigOption::updateOrCreate(
            [
                'config_attribute_id' => $screenCover->id,
                'code' => 'D2',
            ],
            [
                'label' => 'Ductile Iron',
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        // 6) Example dependency (for demo only)
        // Forward-only: Pressure Release Outlet (stage 12) -> Screen Cover Material (stage 13)

        // If Pressure Release = Without => Screen Cover: {Polypropylene}
        OptionRule::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'config_option_id' => $prWithout->id,
                'target_attribute_id' => $screenCover->id,
            ],
            [
                'allowed_option_ids' => [$screenP2->id],
            ],
        );

        // If Pressure Release = Brass Ball Valve => Screen Cover: {Polypropylene, Ductile Iron}
        OptionRule::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'config_option_id' => $prBrassBall->id,
                'target_attribute_id' => $screenCover->id,
            ],
            [
                'allowed_option_ids' => [$screenP2->id, $screenDi2->id],
            ],
        );

        OptionRule::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'config_option_id' => $flangeDin16->id,
                'target_attribute_id' => $kvBody->id,
            ],
            [
                'allowed_option_ids' => [$kvBodyS6->id, $kvBodyDx->id],
            ],
        );

        // Example: if Flange = DIN 16 → only Stainless 316 & Duplex allowed for Kinetic Body
        OptionRule::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'config_option_id' => $flangeDin16->id,
                'target_attribute_id' => $kvBody->id,
            ],
            [
                'allowed_option_ids' => [$kvBodyS6->id, $kvBodyDx->id],
            ],
        );

        // Example: if Kinetic Body = Duplex 5A → only Viton allowed for Kinetic Seal
        OptionRule::updateOrCreate(
            [
                'config_profile_id' => $configProfile->id,
                'config_option_id' => $kvBodyDx->id,
                'target_attribute_id' => $kvSeal->id,
            ],
            [
                'allowed_option_ids' => [$kvSealVt->id],
            ],
        );

        // 7) Parts (BOM master data)
        $parts = collect([
            [
                'code' => 'D60S-BODY-DI',
                'name' => 'Body',
                'description' => 'Valve main body',
                'default_material' => 'Ductile Iron',
                'is_active' => true,
            ],
            [
                'code' => 'D60S-COVER-DI',
                'name' => 'Cover',
                'description' => 'Top cover',
                'default_material' => 'Ductile Iron',
                'is_active' => true,
            ],
            [
                'code' => 'D60S-FLOAT-PP',
                'name' => 'Float',
                'description' => 'Float assembly',
                'default_material' => 'Polypropylene',
                'is_active' => true,
            ],
            [
                'code' => 'D60S-SEAL-EPDM',
                'name' => 'Seal Kit',
                'description' => 'Seal kit',
                'default_material' => 'EPDM',
                'is_active' => true,
            ],
            [
                'code' => 'D60S-BOLTS-SS',
                'name' => 'Fasteners Set',
                'description' => 'Bolts, nuts, and washers set',
                'default_material' => 'Stainless Steel',
                'is_active' => true,
            ],
        ])->mapWithKeys(function (array $data): array {
            $part = Part::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'default_material' => $data['default_material'],
                    'is_active' => $data['is_active'],
                ],
            );

            return [$data['code'] => $part];
        });

        // 8) Saved configuration instances
        $selectionA = [
            $flange->id => $flangeDin16->id,
            $kvBody->id => $kvBodyS6->id,
            $kvSeal->id => $kvSealEp->id,
            $kvSeat->id => $kvSeatBr->id,
            $kvFloat->id => $kvFloatPc->id,
            $pressureRelease->id => $prBrassBall->id,
            $screenCover->id => $screenP2->id,
        ];

        $configurationA = ProductConfiguration::updateOrCreate(
            [
                'product_profile_id' => $profile->id,
                'configuration_code' => 'D60S-P16-03-DEMO-A',
            ],
            [
                'name' => 'D60S Demo Configuration A',
                'is_active' => true,
                'drawing_image_path' => 'demo/configurations/d60s-p16-03-demo-a/drawing.png',
                'config_data' => $selectionA,
            ],
        );

        // 9) Configuration parts (BOM lines)
        // Based on the legacy demo dataset shared by the user (kept as simple label/material lines).
        $bomLines = [
            ['part_number' => 1, 'label' => 'Discharge Outlet', 'material' => 'Polyethylene', 'quantity' => null, 'unit' => null],
            ['part_number' => 2, 'label' => 'Bolt', 'material' => 'Stainless Steel SAE 316', 'quantity' => null, 'unit' => null],
            ['part_number' => 3, 'label' => 'Angle Support', 'material' => 'Stainless Steel SAE 316', 'quantity' => null, 'unit' => null],
            ['part_number' => 4, 'label' => 'Ring', 'material' => 'Steel DIN ST.37', 'quantity' => null, 'unit' => null],
            ['part_number' => 5, 'label' => 'Non-Slam Disc', 'material' => 'Ductile Iron', 'quantity' => null, 'unit' => null],
            ['part_number' => 6, 'label' => 'Non Slam Housing', 'material' => 'Polyethylene', 'quantity' => null, 'unit' => null],
            ['part_number' => 7, 'label' => 'Orifice Seat', 'material' => 'Stainless Steel SAE 316', 'quantity' => null, 'unit' => null],
            ['part_number' => 8, 'label' => 'Orifice Seal', 'material' => 'EPDM', 'quantity' => null, 'unit' => null],
            ['part_number' => 9, 'label' => 'Bolt, Nut & Washer', 'material' => 'Galvanized Steel', 'quantity' => null, 'unit' => null],
            ['part_number' => 10, 'label' => 'Cover', 'material' => 'Duct. Iron A536 60-40-18', 'quantity' => null, 'unit' => null],
            ['part_number' => 11, 'label' => 'O-Ring', 'material' => 'Buna-N', 'quantity' => null, 'unit' => null],
            ['part_number' => 12, 'label' => 'Float', 'material' => 'Polycarbonate', 'quantity' => null, 'unit' => null],
            ['part_number' => 13, 'label' => 'Body', 'material' => 'Duct. Iron A536 60-40-18', 'quantity' => null, 'unit' => null],
            ['part_number' => 14, 'label' => 'Float', 'material' => 'Foamed Polypropylene', 'quantity' => null, 'unit' => null],
            ['part_number' => 15, 'label' => 'Rolling Seal', 'material' => 'EPDM', 'quantity' => null, 'unit' => null],
            ['part_number' => 16, 'label' => 'Clamping Stem', 'material' => 'Polypropylene', 'quantity' => null, 'unit' => null],
            ['part_number' => 17, 'label' => 'Body', 'material' => 'Reinforced Nylon', 'quantity' => null, 'unit' => null],
            ['part_number' => 18, 'label' => 'Extension', 'material' => 'Reinforced Nylon', 'quantity' => null, 'unit' => null],
            ['part_number' => 19, 'label' => 'O-Ring', 'material' => 'Buna-N', 'quantity' => null, 'unit' => null],
            ['part_number' => 20, 'label' => 'O-Ring', 'material' => 'Buna-N', 'quantity' => null, 'unit' => null],
            ['part_number' => 21, 'label' => 'Base', 'material' => 'Brass ASTM B-124', 'quantity' => null, 'unit' => null],
            ['part_number' => 22, 'label' => 'Strainer', 'material' => 'Nylon', 'quantity' => null, 'unit' => null],
            ['part_number' => 23, 'label' => 'Coupler', 'material' => 'Brass', 'quantity' => null, 'unit' => null],
            ['part_number' => 24, 'label' => 'Adaptor', 'material' => 'Brass', 'quantity' => null, 'unit' => null],
            ['part_number' => 25, 'label' => 'Air Release Outlet', 'material' => 'Polypropylene, BSPT', 'quantity' => null, 'unit' => null],
            ['part_number' => 26, 'label' => 'Press. Release Outlet', 'material' => 'Without', 'quantity' => null, 'unit' => null],
        ];

        foreach ($bomLines as $i => $line) {
            ConfigurationPart::updateOrCreate(
                [
                    'product_configuration_id' => $configurationA->id,
                    'part_number' => $line['part_number'],
                ],
                [
                    'part_id' => null,
                    'label' => $line['label'],
                    'material' => $line['material'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'segment_index' => null,
                    'notes' => null,
                    'sort_order' => $i + 1,
                ],
            );
        }

        // 10) Configuration specifications (spec sheet lines)
        $specLines = [
            ['spec_group' => 'Dimensions', 'key' => 'A', 'value' => '426 mm (16.78″)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'B', 'value' => '750 mm (29.55″)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'C', 'value' => '6″ (150mm)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'E', 'value' => '236 mm (9.29″)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'D', 'value' => '1/8″', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'ØD', 'value' => '11″ (280 mm)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'ØG', 'value' => '3/4″ (22.5 mm)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'ØK', 'value' => '9–1/2″ (241.3 mm)', 'unit' => null],
            ['spec_group' => 'Dimensions', 'key' => 'Holes', 'value' => '8', 'unit' => null],

            ['spec_group' => 'Specifications', 'key' => 'Series', 'value' => 'D060', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Working Pressure', 'value' => '16 bar (250 psi)', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Valve Type', 'value' => 'Non Slam', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Connection Type', 'value' => 'Flange', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Flange Standard', 'value' => 'ASA 150', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Connection Size', 'value' => '6″', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Automatic Type', 'value' => 'Composite', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Auto Flow Type', 'value' => 'Standard Flow', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Max Temp', 'value' => '60°C (140°F)', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Weight', 'value' => '91 Kg (200.65 Lbs) ±5%', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'AV', 'value' => '17662 mm² (27.376 Sq.in)', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Orifice Area', 'value' => '12 mm² (0.019 Sq.in)', 'unit' => null],
            ['spec_group' => 'Specifications', 'key' => 'Coating', 'value' => 'FBE Blue', 'unit' => null],
        ];

        foreach ($specLines as $i => $line) {
            ConfigurationSpecification::updateOrCreate(
                [
                    'product_configuration_id' => $configurationA->id,
                    'key' => $line['key'],
                ],
                [
                    'spec_group' => $line['spec_group'],
                    'value' => $line['value'],
                    'unit' => $line['unit'],
                    'sort_order' => $i + 1,
                ],
            );
        }

        // 11) File attachments (polymorphic)
        FileAttachment::updateOrCreate(
            [
                'attachable_type' => CatalogGroup::class,
                'attachable_id' => $group->id,
                'title' => 'Main Image',
            ],
            [
                'file_path' => 'demo/catalog/combination-air-valves/main.jpg',
                'file_type' => FileAttachmentType::MainImage,
                'mime_type' => 'image/jpeg',
                'sort_order' => 0,
                'is_primary' => true,
            ],
        );

        FileAttachment::updateOrCreate(
            [
                'attachable_type' => CatalogGroup::class,
                'attachable_id' => $group->id,
                'title' => 'Gallery Image 1',
            ],
            [
                'file_path' => 'demo/catalog/combination-air-valves/gallery-1.jpg',
                'file_type' => FileAttachmentType::GalleryImage,
                'mime_type' => 'image/jpeg',
                'sort_order' => 1,
                'is_primary' => false,
            ],
        );

        FileAttachment::updateOrCreate(
            [
                'attachable_type' => CatalogGroup::class,
                'attachable_id' => $group->id,
                'title' => 'Overview',
            ],
            [
                'file_path' => 'demo/catalog/combination-air-valves/overview.pdf',
                'file_type' => FileAttachmentType::Media,
                'mime_type' => 'application/pdf',
                'sort_order' => 1,
                'is_primary' => true,
            ],
        );

        FileAttachment::updateOrCreate(
            [
                'attachable_type' => ProductProfile::class,
                'attachable_id' => $profile->id,
                'title' => 'Datasheet',
            ],
            [
                'file_path' => 'demo/products/d60s/datasheet.pdf',
                'file_type' => FileAttachmentType::Datasheet,
                'mime_type' => 'application/pdf',
                'sort_order' => 1,
                'is_primary' => true,
            ],
        );

        FileAttachment::updateOrCreate(
            [
                'attachable_type' => ProductProfile::class,
                'attachable_id' => $profile->id,
                'title' => 'Main Image',
            ],
            [
                'file_path' => 'demo/products/d60s/main.jpg',
                'file_type' => FileAttachmentType::MainImage,
                'mime_type' => 'image/jpeg',
                'sort_order' => 0,
                'is_primary' => true,
            ],
        );

        FileAttachment::updateOrCreate(
            [
                'attachable_type' => ProductConfiguration::class,
                'attachable_id' => $configurationA->id,
                'title' => 'Specification Sheet',
            ],
            [
                'file_path' => 'demo/configurations/d60s-p16-03-demo-a/spec-sheet.pdf',
                'file_type' => FileAttachmentType::Specification,
                'mime_type' => 'application/pdf',
                'sort_order' => 1,
                'is_primary' => true,
            ],
        );

        FileAttachment::updateOrCreate(
            [
                'attachable_type' => ProductConfiguration::class,
                'attachable_id' => $configurationA->id,
                'title' => 'Main Image',
            ],
            [
                'file_path' => 'demo/configurations/d60s-p16-03-demo-a/main.jpg',
                'file_type' => FileAttachmentType::MainImage,
                'mime_type' => 'image/jpeg',
                'sort_order' => 0,
                'is_primary' => true,
            ],
        );
    }
}
