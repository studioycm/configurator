<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveValueTags
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $originalTags
     */
    public function handle(User $actor, int $valueId, string $contextTag, array $tags, array $originalTags): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $tags = array_map(fn (mixed $tag): mixed => is_string($tag) ? trim($tag) : $tag, $tags);
        Validator::make(['data' => ['tags' => $tags]], [
            'data.tags' => ['present', 'array', 'list', 'max:30'],
            'data.tags.*' => ['required', 'string', 'max:80', 'distinct:strict'],
        ])->validate();

        DB::transaction(function () use ($valueId, $contextTag, $tags, $originalTags): void {
            $value = Value::query()->whereKey($valueId)->lockForUpdate()->firstOrFail();
            if (($value->tags ?? []) !== $originalTags || ! in_array($contextTag, $value->tags ?? [], true)) {
                throw ValidationException::withMessages(['data.tags' => __('tags.stale_row')]);
            }
            $value->tags = $tags;
            $value->save();
        }, attempts: 3);
    }
}
