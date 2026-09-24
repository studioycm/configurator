<?php

namespace App\Services;

use Closure;

class CatalogFilterReconciler
{
    /**
     * @param  array<string, array{property: string, values: list<string>}>  $constraints
     * @param  list<string>  $precedence  Oldest to newest.
     * @param  Closure(array<string, array{property: string, values: list<string>}>): bool  $exists
     * @return array{kept: list<string>, removed: list<string>}
     */
    public function reconcile(array $constraints, array $precedence, Closure $exists): array
    {
        $accepted = [];
        $removed = [];
        foreach (array_reverse($precedence) as $token) {
            $proposed = $accepted + [$token => $constraints[$token]];
            if ($exists($proposed)) {
                $accepted = $proposed;
            } else {
                $removed[] = $token;
            }
        }

        return ['kept' => array_reverse(array_keys($accepted)), 'removed' => $removed];
    }
}
