<?php

namespace App\Actions;

use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\User;
use App\Services\CatalogIntegrity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveGroupTags
{
    public function __construct(private CatalogIntegrity $integrity) {}

    /** @param array<string, mixed> $data */
    public function saveCategory(User $actor, ?GroupTagCategory $category, array $data): GroupTagCategory
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $validated = $this->validateVocabulary($data, 100, false);

        return DB::transaction(function () use ($category, $validated): GroupTagCategory {
            $record = $category === null ? new GroupTagCategory : GroupTagCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $record->fill($validated);
            if (! $record->exists || $record->isDirty()) {
                $record->save();
            }

            return $record;
        }, attempts: 1);
    }

    /** @param array<string, mixed> $data */
    public function saveValue(User $actor, ?GroupTagValue $value, array $data): GroupTagValue
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $validated = $this->validateVocabulary($data, 150, true);
        if ($value === null && ! array_key_exists('group_tag_category_id', $validated)) {
            throw ValidationException::withMessages(['group_tag_category_id' => 'Choose a current tag category.']);
        }

        return DB::transaction(function () use ($value, $validated): GroupTagValue {
            $categoryId = $value === null ? $validated['group_tag_category_id'] : GroupTagValue::query()->whereKey($value->id)->firstOrFail()->group_tag_category_id;
            $category = GroupTagCategory::query()->whereKey($categoryId)->lockForUpdate()->first();
            if ($category === null) {
                throw ValidationException::withMessages(['group_tag_category_id' => 'Choose a current tag category.']);
            }
            $record = $value === null ? new GroupTagValue(['group_tag_category_id' => $category->id]) : GroupTagValue::query()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            if ($record->group_tag_category_id !== (int) $category->id || (isset($validated['group_tag_category_id']) && $validated['group_tag_category_id'] !== $record->group_tag_category_id)) {
                throw ValidationException::withMessages(['group_tag_category_id' => 'A tag value cannot move to another category.']);
            }
            $record->fill($validated);
            if (! $record->exists || $record->isDirty()) {
                $record->save();
            }

            return $record;
        }, attempts: 1);
    }

    public function fingerprint(Group $group): string
    {
        return $this->hashAssignment((int) $group->id, $this->assignmentRows((int) $group->id));
    }

    public function removeCategory(User $actor, GroupTagCategory $category): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        DB::transaction(function () use ($category): void {
            $groups = $this->integrity->lockGroups();
            $shared = CatalogPresentationSettings::query()->whereKey(1)->lockForUpdate()->first();
            $record = GroupTagCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $values = $record->values()->orderBy('id')->lockForUpdate()->get(['id']);
            $references = [$shared?->settings];
            foreach ($groups as $group) {
                $references[] = $group->result_settings['catalog_presentation'] ?? null;
            }
            foreach ($references as $settings) {
                if (is_array($settings) && is_array($settings['group_card_tag_categories'] ?? null)
                    && in_array((int) $record->id, $settings['group_card_tag_categories'], true)) {
                    throw ValidationException::withMessages(['tag_category' => 'Remove the category from Catalog Display settings before deleting it.']);
                }
            }
            if ($values->isNotEmpty()) {
                throw ValidationException::withMessages(['tag_category' => 'Remove all values before deleting this category.']);
            }
            $record->delete();
        }, attempts: 1);
    }

    public function removeValue(User $actor, GroupTagValue $value): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        DB::transaction(function () use ($value): void {
            $this->integrity->lockGroups();
            CatalogPresentationSettings::query()->whereKey(1)->lockForUpdate()->first();
            $categoryId = GroupTagValue::query()->whereKey($value->id)->firstOrFail()->group_tag_category_id;
            GroupTagCategory::query()->whereKey($categoryId)->lockForUpdate()->firstOrFail();
            $record = GroupTagValue::query()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            $assignments = DB::table('group_tag_value')->where('group_tag_value_id', $record->id)->orderBy('group_id')->lockForUpdate()->get(['group_id']);
            if ($assignments->isNotEmpty()) {
                throw ValidationException::withMessages(['tag_value' => 'Unassign this value from all Groups before deleting it.']);
            }
            $record->delete();
        }, attempts: 1);
    }

    /** @param list<int> $orderedValueIds */
    public function assign(User $actor, Group $group, array $orderedValueIds, string $expectedTagFingerprint): Group
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! array_is_list($orderedValueIds) || count(array_unique($orderedValueIds, SORT_REGULAR)) !== count($orderedValueIds)
            || array_filter($orderedValueIds, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            throw ValidationException::withMessages(['tag_values' => 'Choose distinct current tag value IDs in order.']);
        }

        return DB::transaction(function () use ($group, $orderedValueIds, $expectedTagFingerprint): Group {
            $record = $this->integrity->lockGroups()->get($group->id);
            abort_if($record === null, 404);
            $categoryIds = GroupTagValue::query()->whereKey($orderedValueIds)->pluck('group_tag_category_id')->unique()->sort()->values()->all();
            $categories = GroupTagCategory::query()->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $values = GroupTagValue::query()->whereKey($orderedValueIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($values->count() !== count($orderedValueIds) || $values->contains(fn (GroupTagValue $value): bool => ! $categories->has($value->group_tag_category_id))) {
                throw ValidationException::withMessages(['tag_values' => 'A selected tag value is no longer available.']);
            }
            $current = $this->hashAssignment((int) $record->id, $this->assignmentRows((int) $record->id, true));
            if (! hash_equals($current, $expectedTagFingerprint)) {
                throw ValidationException::withMessages(['tag_values' => 'Group tags changed. Reload them before saving.']);
            }
            $rows = [];
            foreach ($orderedValueIds as $position => $id) {
                $rows[] = ['value_id' => $id, 'category_id' => $values[$id]->group_tag_category_id, 'position' => $position];
            }
            if (! hash_equals($current, $this->hashAssignment((int) $record->id, $rows))) {
                $record->tagValues()->sync(array_column(array_map(fn (array $row): array => ['id' => $row['value_id'], 'pivot' => ['position' => $row['position']]], $rows), 'pivot', 'id'));
            }

            return $record;
        }, attempts: 1);
    }

    /** @return list<array{value_id:int,category_id:int,position:int}> */
    private function assignmentRows(int $groupId, bool $lock = false): array
    {
        $query = DB::table('group_tag_value')->join('group_tag_values', 'group_tag_values.id', '=', 'group_tag_value.group_tag_value_id')
            ->where('group_tag_value.group_id', $groupId)->orderBy('group_tag_value.position')->orderBy('group_tag_value.group_tag_value_id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get(['group_tag_value.group_tag_value_id as value_id', 'group_tag_values.group_tag_category_id as category_id', 'group_tag_value.position'])
            ->map(fn (object $row): array => ['value_id' => (int) $row->value_id, 'category_id' => (int) $row->category_id, 'position' => (int) $row->position])->all();
    }

    /** @param list<array{value_id:int,category_id:int,position:int}> $rows */
    private function hashAssignment(int $groupId, array $rows): string
    {
        return hash('sha256', json_encode(['group_id' => $groupId, 'values' => $rows], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function validateVocabulary(array $data, int $labelLimit, bool $value): array
    {
        foreach (['sort_order', ...($value ? ['group_tag_category_id'] : [])] as $field) {
            if (array_key_exists($field, $data) && (! is_int($data[$field]) && ! is_string($data[$field]))) {
                throw ValidationException::withMessages([$field => 'Use an integer value.']);
            }
        }
        if (isset($data['label']) && is_string($data['label']) && preg_match('/[^\p{Z}\s]/u', $data['label']) !== 1) {
            throw ValidationException::withMessages(['label' => 'A tag label must contain text.']);
        }
        $rules = ['data' => ['required', 'array:label,sort_order'.($value ? ',group_tag_category_id' : '')],
            'data.label' => ['required', 'string', 'max:'.$labelLimit], 'data.sort_order' => ['required', 'integer', 'min:0', 'max:4294967295']];
        if ($value) {
            $rules['data.group_tag_category_id'] = ['sometimes', 'required', 'integer', 'min:1'];
        }
        $validated = Validator::make(['data' => $data], $rules)->validate()['data'];
        $validated['sort_order'] = (int) $validated['sort_order'];
        if (isset($validated['group_tag_category_id'])) {
            $validated['group_tag_category_id'] = (int) $validated['group_tag_category_id'];
        }

        return $validated;
    }
}
