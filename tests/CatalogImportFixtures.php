<?php

use App\Actions\ImportCatalogProducts;
use App\Models\User;

function catalogFixtureRow(string $id = '00017', string $code = '0042-Aa'): array
{
    $row = array_fill(0, 67, '');
    foreach ([0 => $id, 1 => '2144', 2 => 'D060', 3 => $code, 4 => 'A test valve', 7 => '25 bar', 10 => '1″', 42 => 'Test air valve', 43 => '0206', 47 => '', 54 => 'a:1:{i:0;s:1:"0";}', 66 => ''] as $position => $value) {
        $row[$position] = $value;
    }
    foreach ([18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 53, 58, 59, 60, 61, 62, 63, 64, 65] as $slot => $position) {
        $row[$position] = 'Slot '.($slot + 1);
    }
    $row[18] = 'Outlet<Polypropylene>';
    $row[19] = '&lt;still encoded&gt;';

    return $row;
}

function catalogFixtureCsv(array $rows, ?array $headers = null): string
{
    $headers ??= explode(',', 'id,group_id,group_name,product_code,Description,product_pic,sub_group,Working_Pressure,Valve_Type,Connection_Type,Connection_Size,Automatic_Type,Automatic_Config,Discharge_Outlet_Type,Addition_to_Product_Code,Strainer,Model,Max_W_Temp,Part1,Part2,Part3,Part4,Part5,Part6,Part7,Part8,Part9,Part10,Part11,Part12,Part13,Part14,Part15,Part16,Part17,Part18,Part19,A,B,OA,WT,AV,Product_Name,Indc,Auto_Flow_Type,E,D_out,,MyIndc,blank1,blank2,blank3,Connection_Type_Layout,Part20,product_supplier,product_standard,standard_compliancy,C,Part21,Part22,Part23,Part24,Part25,Part26,Part27,Part28,with_s50c');
    $path = tempnam(sys_get_temp_dir(), 'catalog-fixture-');
    $stream = fopen($path, 'w');
    foreach ([$headers, ...$rows] as $row) {
        fputcsv($stream, $row, escape: '');
    }
    fclose($stream);
    test()->beforeApplicationDestroyed(fn () => unlink($path));

    return $path;
}

function applyCatalogFixture(User $actor, string $path, array $parents = []): array
{
    $action = app(ImportCatalogProducts::class);
    $review = $action->handle($actor, $path, parents: $parents);

    return $action->handle($actor, $path, true, $review['source_hash'], $review['map_hash'], $parents, $review['parents_hash']);
}
