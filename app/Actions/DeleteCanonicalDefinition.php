<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\CanonicalUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteCanonicalDefinition
{
    public function __construct(private CanonicalUsage $usage) {}

    public function handle(User $actor, Attribute|Value|Option $record): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        DB::transaction(function () use ($record): void {
            $configurators = $this->usage->configurators($record)->orderBy('id')->lockForUpdate()->get();
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $used = $record instanceof Option ? $record->inclusions()->exists() : $record->options()->exists();
            if ($record instanceof Attribute) {
                $used = $used || $record->inclusions()->exists();
            }
            if ($used) {
                $names = $configurators->take(5)->pluck('name')->implode(', ');
                throw ValidationException::withMessages(['record' => 'This shared definition is in use'.($names === '' ? '' : ' by '.$names).'. View usage and repair its Options, defaults and rules before removing it.']);
            }
            $record->delete();
        });
    }
}
