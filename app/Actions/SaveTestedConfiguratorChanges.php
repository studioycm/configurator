<?php

namespace App\Actions;

use App\Models\Configurator;
use App\Models\User;
use App\Services\CatalogRevisions;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveTestedConfiguratorChanges
{
    /** @param list<array<string, mixed>> $captures @param array<string, mixed> $runtime */
    public function handle(User $actor, int $configuratorId, int $productId, array $captures, string $fingerprint, array $runtime): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($actor, $configuratorId, $productId, $captures, $fingerprint, $runtime): array {
            $configurator = Configurator::whereKey($configuratorId)->lockForUpdate()->firstOrFail();
            $changes = app(ConfiguratorEditorChanges::class);
            if (! hash_equals($fingerprint, $changes->fingerprint($configurator, $productId, lock: true))) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.saved_conflict')]);
            }
            $prepared = $changes->prepare($configurator, $captures);
            if ($prepared['changes'] === []) {
                throw ValidationException::withMessages(['preview' => __('configurator.preview.no_unsaved')]);
            }
            $input = app(ConfiguratorDefinitionLoader::class)->forPreview($actor, $configuratorId, $productId, $runtime, ['kind' => 'Reevaluate'], $prepared['data']);
            $issues = $changes->blockingIssues(app(ConfiguratorEngine::class)->evaluate($input));
            if ($issues !== []) {
                throw ValidationException::withMessages(['preview' => $issues]);
            }
            app(CatalogRevisions::class)->batch(fn () => app(SaveConfiguratorDefinition::class)->handle($actor, $configurator, $prepared['data']));
            $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
            $newRules = [];
            foreach ($prepared['data']['rules'] as $rule) {
                if (str_starts_with($rule['id'], 'new:')) {
                    $newRules[$rule['id']] = collect($saved['rules'])->firstWhere('priority', $rule['priority']);
                }
            }

            return $newRules;
        }, attempts: 3);
    }
}
