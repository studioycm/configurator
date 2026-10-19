<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Value;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageValueTags
{
    /** @return array{token: string, affected: int, destination_usage: int, rows: array} */
    public function preview(User $actor, string $operation, string $source, ?string $target = null): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        [$source, $target] = $this->validate($operation, $source, $target);

        return $this->snapshot($actor, $operation, $source, $target, Value::query()->orderBy('id')->get(['id', 'label', 'tags']));
    }

    public function apply(User $actor, string $operation, string $source, ?string $target, string $token): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        [$source, $target] = $this->validate($operation, $source, $target);
        DB::transaction(function () use ($actor, $operation, $source, $target, $token): void {
            $records = Value::query()->orderBy('id')->lockForUpdate()->get(['id', 'label', 'tags']);
            $preview = $this->snapshot($actor, $operation, $source, $target, $records);
            if (! hash_equals($preview['token'], $token)) {
                throw ValidationException::withMessages(['preview' => __('tags.stale_preview')]);
            }
            foreach ($records as $record) {
                if (in_array($source, $record->tags ?? [], true)) {
                    $record->tags = $this->transform($record->tags, $source, $target);
                    $record->save();
                }
            }
        }, attempts: 3);
    }

    /** @return array{string, ?string} */
    private function validate(string $operation, string $source, ?string $target): array
    {
        $target = $operation === 'remove' ? null : trim($target ?? '');
        Validator::make(compact('operation', 'source', 'target'), [
            'operation' => ['required', Rule::in(['rename', 'merge', 'remove'])],
            'source' => ['required', 'string', 'max:80'],
            'target' => [$operation === 'remove' ? 'nullable' : 'required', 'string', 'max:80', 'different:source'],
        ])->validate();

        return [$source, $target];
    }

    /** @param Collection<int, Value> $records @return array{token: string, affected: int, destination_usage: int, rows: array} */
    private function snapshot(User $actor, string $operation, string $source, ?string $target, Collection $records): array
    {
        $sources = $records->filter(fn (Value $value): bool => in_array($source, $value->tags ?? [], true));
        $targets = $target === null ? collect() : $records->filter(fn (Value $value): bool => in_array($target, $value->tags ?? [], true));
        if ($sources->isEmpty()) {
            throw ValidationException::withMessages(['source' => __('tags.source_missing')]);
        }
        if (($operation === 'rename' && $targets->isNotEmpty()) || ($operation === 'merge' && $targets->isEmpty())) {
            throw ValidationException::withMessages(['target' => __($operation === 'rename' ? 'tags.use_merge' : 'tags.target_missing')]);
        }
        $states = $records->filter(fn (Value $value): bool => in_array($source, $value->tags ?? [], true) || ($target !== null && in_array($target, $value->tags ?? [], true)))
            ->map(fn (Value $value): array => $value->only(['id', 'label', 'tags']))->values()->all();
        $rows = $sources->take(10)->map(fn (Value $value): array => ['id' => $value->id, 'label' => $value->label, 'before' => $value->tags, 'after' => $this->transform($value->tags, $source, $target)])->values()->all();

        return [
            'token' => hash_hmac('sha256', json_encode([$actor->id, $operation, $source, $target, $states], JSON_THROW_ON_ERROR), config('app.key')),
            'affected' => $sources->count(), 'destination_usage' => $targets->count(), 'rows' => $rows,
        ];
    }

    /** @param list<string> $tags @return list<string> */
    private function transform(array $tags, string $source, ?string $target): array
    {
        return array_values(array_unique(array_filter(array_map(fn (string $tag): ?string => $tag === $source ? $target : $tag, $tags), fn (?string $tag): bool => $tag !== null)));
    }
}
