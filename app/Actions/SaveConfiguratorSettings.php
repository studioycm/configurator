<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveConfiguratorSettings
{
    public function __construct(private SaveConfiguratorDefinition $definitions) {}

    /** @param array<string, mixed> $settings */
    public function handle(User $actor, Configurator $configurator, array $settings): Configurator
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (array_diff(array_keys($settings), ['name', 'description', 'context_schema']) !== []) {
            throw ValidationException::withMessages(['settings' => 'Settings can change only the name, description and context choices.']);
        }

        return $this->definitions->change($actor, $configurator, fn (array $draft): array => [...$draft, ...$settings]);
    }
}
