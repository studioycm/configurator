<?php

use App\Services\CatalogFilterReconciler;

test('newest first replay keeps compatible older constraints with independent B and C outcomes', function (array $order, array $expected) {
    $rows = [['pressure' => '25', 'connection' => 'Flange', 'size' => '2'], ['pressure' => '16', 'connection' => 'Flange', 'size' => '1'], ['pressure' => '25', 'connection' => 'Threaded', 'size' => '1']];
    $constraints = ['pressure' => ['property' => 'pressure', 'values' => ['25']], 'connection' => ['property' => 'connection', 'values' => ['Flange']], 'size' => ['property' => 'size', 'values' => ['1']]];
    $result = (new CatalogFilterReconciler)->reconcile($constraints, $order, function (array $accepted) use ($rows): bool {
        foreach ($rows as $row) {
            if (array_all($accepted, fn (array $constraint): bool => in_array($row[$constraint['property']], $constraint['values'], true))) {
                return true;
            }
        }

        return false;
    });
    expect($result['kept'])->toBe($expected);
})->with([
    'B keeps connection' => [['pressure', 'connection', 'size'], ['connection', 'size']],
    'C keeps pressure' => [['connection', 'pressure', 'size'], ['pressure', 'size']],
]);
