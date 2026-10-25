<?php

namespace App\Actions;

use App\CatalogWriteFailure;
use App\Models\Configurator;
use App\Models\User;
use App\Services\CatalogRevisions;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

class SaveTestedConfiguratorChanges
{
    /** @param list<array<string, mixed>> $captures @param array<string, mixed> $runtime */
    public function handle(User $actor, int $configuratorId, int $productId, array $captures, string $fingerprint, array $runtime): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        $attempt = 0;
        while (true) {
            $attempt++;
            $bodyFailure = null;
            try {
                return DB::transaction(function () use ($actor, $configuratorId, $productId, $captures, $fingerprint, $runtime, &$bodyFailure): array {
                    try {
                        return $this->saveTested($actor, $configuratorId, $productId, $captures, $fingerprint, $runtime);
                    } catch (Throwable $exception) {
                        $bodyFailure = $exception;
                        throw $exception;
                    }
                }, attempts: 1);
            } catch (Throwable $exception) {
                $rolledBack = $exception === $bodyFailure;
                if ($rolledBack && ($exception instanceof ValidationException || $exception instanceof AuthorizationException)) {
                    throw $exception;
                }
                $cause = $exception;
                $driverCode = null;
                do {
                    if ($cause instanceof PDOException && isset($cause->errorInfo[1])) {
                        $driverCode = (int) $cause->errorInfo[1];
                        break;
                    }
                    $cause = $cause->getPrevious();
                } while ($cause !== null);
                if ($rolledBack && $attempt < 3 && in_array($driverCode, [1205, 1213], true)) {
                    continue;
                }
                throw new CatalogWriteFailure($rolledBack, $exception);
            }
        }
    }

    /** @param list<array<string, mixed>> $captures @param array<string, mixed> $runtime */
    private function saveTested(User $actor, int $configuratorId, int $productId, array $captures, string $fingerprint, array $runtime): array
    {
        $configurator = Configurator::whereKey($configuratorId)->lockForUpdate()->firstOrFail();
        $changes = app(ConfiguratorEditorChanges::class);
        if (! hash_equals($fingerprint, $changes->fingerprint($configurator, $productId, lock: true))) {
            throw ValidationException::withMessages(['preview' => __('configurator.preview.saved_conflict')]);
        }
        $prepared = $changes->prepare($actor, $configurator, $captures);
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
    }
}
