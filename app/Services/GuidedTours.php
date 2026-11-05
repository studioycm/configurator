<?php

namespace App\Services;

use App\Models\TourDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GuidedTours
{
    /** @return array<string, string> */
    public static function pages(): array
    {
        return ['images' => __('catalog-images.title')];
    }

    /** @return array<string, string> */
    public static function targets(): array
    {
        return ['images.find' => __('tours.targets.find'), 'images.records' => __('tours.targets.records'), 'images.inspector' => __('tours.targets.inspector')];
    }

    /** @return array<string, string> */
    public static function placements(): array
    {
        return ['bottom' => __('tours.bottom'), 'top' => __('tours.top'), 'left' => __('tours.left'), 'right' => __('tours.right')];
    }

    /** @param array<string, mixed> $input */
    public function save(User $actor, ?TourDefinition $record, array $input): TourDefinition
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return DB::transaction(function () use ($record, $input): TourDefinition {
            $tour = $record?->exists ? TourDefinition::query()->lockForUpdate()->findOrFail($record->id) : new TourDefinition;
            $data = Validator::make($input, [
                'key' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('tour_definitions')->where('locale', $input['locale'] ?? '')->ignore($tour)],
                'title' => ['required', 'string', 'max:255'],
                'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/'],
                'enabled' => ['required', 'boolean'],
                'steps' => ['required', 'array', 'min:1', 'max:30'],
                'steps.*' => ['required', 'array:key,page,target,title,description,placement'],
                'steps.*.key' => ['required', 'string', 'alpha_dash:ascii', 'max:100', 'distinct'],
                'steps.*.page' => ['required', Rule::in(array_keys(self::pages()))],
                'steps.*.target' => ['required', Rule::in(array_keys(self::targets()))],
                'steps.*.title' => ['required', 'string', 'max:255'],
                'steps.*.description' => ['required', 'string', 'max:2000'],
                'steps.*.placement' => ['required', Rule::in(array_keys(self::placements()))],
            ])->validate();
            $data['steps'] = array_values($data['steps']);
            $tour->fill($data);
            $tour->revision = $tour->exists ? $tour->revision + 1 : 1;
            $tour->save();

            return $tour;
        });
    }

    /** @return list<array<string, mixed>> */
    public function forPage(?User $actor, string $page, ?string $locale = null): array
    {
        if ($actor === null || ! Gate::forUser($actor)->allows('manage-catalog') || ! array_key_exists($page, self::pages()) || ! config('catalog-assets.enabled') || ! Schema::hasTable('tour_definitions')) {
            return [];
        }
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');
        $headers = TourDefinition::query()->where('enabled', true)->whereIn('locale', [$locale, $fallback])
            ->orderBy('id')->get(['id', 'key', 'locale', 'revision'])->groupBy('key');
        $result = [];
        foreach ($headers as $translations) {
            $header = $translations->firstWhere('locale', $locale) ?? $translations->firstWhere('locale', $fallback);
            $payload = Cache::remember('guided-tour:'.$header->id.':'.$header->revision, 3600, fn (): ?array => TourDefinition::query()
                ->whereKey($header->id)->where('revision', $header->revision)->where('enabled', true)->first(['title', 'steps', 'revision'])?->toArray());
            if ($payload === null) {
                continue;
            }
            $steps = array_values(array_filter($payload['steps'], fn (array $step): bool => $step['page'] === $page));
            if ($steps !== []) {
                $result[] = ['key' => $header->key, 'title' => $payload['title'], 'revision' => $payload['revision'], 'steps' => $steps];
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public static function starter(): array
    {
        return ['key' => 'images-basics', 'title' => 'Images Manager: find and inspect', 'locale' => 'en', 'enabled' => true, 'steps' => [
            ['key' => 'find', 'page' => 'images', 'target' => 'images.find', 'title' => 'Find records', 'description' => 'Choose Products or Groups, then use Image status and search to find records. Missing shows records without a saved main image.', 'placement' => 'bottom'],
            ['key' => 'records', 'page' => 'images', 'target' => 'images.records', 'title' => 'Inspect or select', 'description' => 'Inspect opens one record. Checkboxes build a selection for assigning images together. Inspecting a record does not add it to that selection.', 'placement' => 'top'],
            ['key' => 'inspector', 'page' => 'images', 'target' => 'images.inspector', 'title' => 'Work on one image', 'description' => 'After inspecting a record, this pane shows its saved image and image controls. An upload or chosen replacement remains a draft until Save image.', 'placement' => 'left'],
        ]];
    }
}
