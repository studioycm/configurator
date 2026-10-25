<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Configurator;
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
        $attributeIds = $record instanceof Attribute ? [$record->id] : [];
        $valueIds = $record instanceof Value ? [$record->id] : [];
        $optionIds = $record instanceof Option ? [$record->id] : [];
        $ownerIds = $this->usage->configuratorIds($attributeIds, $valueIds, $optionIds);
        DB::transaction(function () use ($record, $attributeIds, $valueIds, $optionIds, $ownerIds): void {
            $configurators = Configurator::whereKey($ownerIds)->orderBy('id')->lockForUpdate()->get();
            $parents = $this->usage->lockParents($attributeIds, $valueIds, $optionIds);
            $record = $parents[match (true) {
                $record instanceof Attribute => 'attributes', $record instanceof Value => 'values', default => 'options'
            }]->findOrFail($record->id);
            $used = ! $record instanceof Option && $record->options()->orderBy('id')->lockForUpdate()->limit(1)->get(['id'])->isNotEmpty();
            $this->usage->assertConfiguratorIds($ownerIds, $attributeIds, $valueIds, $optionIds);
            if ($record instanceof Option) {
                $used = $record->inclusions()->orderBy('id')->lockForUpdate()->limit(1)->get(['id'])->isNotEmpty();
            }
            if ($record instanceof Attribute) {
                $used = $used || $record->inclusions()->orderBy('id')->lockForUpdate()->limit(1)->get(['id'])->isNotEmpty();
            }
            if ($used) {
                $names = $configurators->take(5)->pluck('name')->implode(', ');
                throw ValidationException::withMessages(['record' => __('catalog.validation.this_shared_definition_is_in_use_view', ['value1' => $names === '' ? '' : ' by '.$names])]);
            }
            $record->delete();
        });
    }
}
