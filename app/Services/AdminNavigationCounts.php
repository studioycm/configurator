<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminNavigationCounts
{
    /** @param class-string<Model> $model */
    public function total(string $model): ?string
    {
        if (! Gate::allows('manage-catalog')) {
            return null;
        }
        if (DB::transactionLevel() > 0) {
            return (string) $model::count();
        }

        return (string) Cache::remember($this->key($model), 60, fn (): int => $model::count());
    }

    /** @param class-string<Model> $model */
    public function forget(string $model): void
    {
        Cache::forget($this->key($model));
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::forget($this->key($model)));
        }
    }

    /** @param class-string<Model> $model */
    private function key(string $model): string
    {
        return 'admin-navigation-total:'.$model;
    }
}
