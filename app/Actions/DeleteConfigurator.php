<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\User;
use App\Services\CatalogIntegrity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteConfigurator
{
    public function __construct(private CatalogIntegrity $integrity, private SaveConfiguratorDefinition $definitions) {}

    public function handle(User $actor, Configurator $configurator): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        DB::transaction(function () use ($actor, $configurator): void {
            $this->integrity->lockGroups();
            $record = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            if ($record->groups()->exists()) {
                throw ValidationException::withMessages(['configurator' => 'Unassign the leaf Groups before deleting this shared Configurator.']);
            }
            $this->definitions->handle($actor, $record, ['name' => $record->name, 'description' => $record->description, 'context_schema' => ['territory' => [], 'application' => []], 'policy_overrides' => [], 'attributes' => [], 'rules' => []]);
            $record->delete();
        });
    }
}
