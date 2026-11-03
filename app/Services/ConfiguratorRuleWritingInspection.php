<?php

namespace App\Services;

use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use LogicException;

class ConfiguratorRuleWritingInspection
{
    public function __construct(private ConfiguratorRuleRecords $records) {}

    /** @return array<string,mixed> A read snapshot, never a save acknowledgement or CAS reconciliation. */
    public function inspect(User $actor, Configurator $configurator, string $draftId): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        Validator::make(['id' => $draftId], ['id' => ['required', 'uuid']])->validate();
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Writing record inspection must own its read transaction.');
        }

        return DB::transaction(function () use ($configurator, $draftId): array {
            $owner = Configurator::whereKey($configurator->id)->sharedLock()->firstOrFail();
            $draft = ConfiguratorRuleWritingDraft::where('configurator_id', $owner->id)->whereKey(strtolower($draftId))->firstOrFail();
            $current = null;
            $target = 'not_saved';
            if ($draft->origin_rule_id !== null) {
                $rule = ConfiguratorRule::where('configurator_id', $owner->id)->whereKey($draft->origin_rule_id)->first();
                if ($rule === null && ConfiguratorRule::whereKey($draft->origin_rule_id)->exists()) {
                    throw (new ModelNotFoundException)->setModel(ConfiguratorRule::class, [$draft->origin_rule_id]);
                }
                $target = $rule === null ? 'deleted' : 'saved';
                if ($rule !== null) {
                    $records = $this->records->read($rule);
                    $current = ['rule' => $records['rule'], 'records' => $records, 'fingerprint' => $this->records->fingerprint($records)];
                }
            }
            $capture = $draft->last_saved_authoring;
            $captureState = 'none';
            $draftChanged = null;
            if ($capture !== null) {
                $fingerprint = $capture['saved_fingerprint'] ?? null;
                $captureState = $current !== null && is_string($fingerprint) && hash_equals($fingerprint, $current['fingerprint']) ? 'matches_current' : 'older_or_different';
                $draftChanged = $draft->source !== ($capture['source'] ?? null) || $draft->label !== ($capture['label'] ?? null) || $draft->description !== ($capture['description'] ?? null);
            }

            return ['configurator_id' => $owner->id, 'draft_id' => $draft->id, 'inspected_at' => now()->utc()->toIso8601String(), 'draft' => $draft->toArray(), 'current' => $current, 'last_writing_capture' => $capture,
                'status' => ['target' => $target, 'capture' => $captureState, 'draft_changed_since_capture' => $draftChanged]];
        }, attempts: 1);
    }
}
