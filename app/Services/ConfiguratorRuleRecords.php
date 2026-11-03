<?php

namespace App\Services;

use App\Models\ConfiguratorRule;
use Illuminate\Support\Facades\DB;

/** Internal SQL read capability; callers authorize, scope the Rule and own snapshot/locks. */
class ConfiguratorRuleRecords
{
    /** @return array<string,mixed> */
    public function read(ConfiguratorRule $rule): array
    {
        $records = ['rule' => $rule->getRawOriginal()];
        foreach (['condition_groups' => 'rule_condition_groups', 'conditions' => 'rule_conditions', 'effects' => 'rule_effects', 'mapping_sets' => 'mapping_sets', 'mapping_sources' => 'mapping_set_sources'] as $key => $table) {
            $records[$key] = DB::table($table)->where('rule_id', $rule->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        foreach (['condition_references' => ['rule_condition_options', 'condition_id', 'conditions'], 'effect_references' => ['rule_effect_options', 'effect_id', 'effects'], 'mapping_targets' => ['mapping_set_targets', 'mapping_set_id', 'mapping_sets']] as $key => [$table, $foreign, $bucket]) {
            $records[$key] = DB::table($table)->whereIn($foreign, array_column($records[$bucket], 'id'))->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $records;
    }

    /** @param array<string,mixed> $records */
    public function fingerprint(array $records): string
    {
        return hash('sha256', json_encode($this->orderedObjects($records), JSON_THROW_ON_ERROR));
    }

    private function orderedObjects(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->orderedObjects(...), $value);
    }
}
