<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateConfigurator
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $validated = Validator::make($data, ['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']])->validate();

        return DB::transaction(fn (): Configurator => Configurator::create([...$validated, 'context_schema' => ['territory' => [], 'application' => []], 'policy_overrides' => []]));
    }
}
