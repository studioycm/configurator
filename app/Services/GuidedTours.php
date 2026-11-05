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
        return array_map(fn (array $screen): string => $screen['title'], config('guided-tours.screens', []));
    }

    /** @param list<string> $scopes */
    public static function pageForScopes(array $scopes): ?string
    {
        foreach ($scopes as $scope) {
            foreach (config('guided-tours.screens', []) as $page => $screen) {
                if ($screen['scope'] === $scope) {
                    return $page;
                }
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function targets(?string $page = null): array
    {
        $targets = ['images.find' => __('tours.targets.find'), 'images.records' => __('tours.targets.records'), 'images.inspector' => __('tours.targets.inspector'), 'screen.heading' => __('tours.targets.heading'), 'screen.workspace' => __('tours.targets.workspace')];

        return $page === null ? $targets : array_intersect_key($targets, self::selectors($page));
    }

    /** @return array<string, string> */
    public static function selectors(string $page): array
    {
        if (! array_key_exists($page, self::pages())) {
            return [];
        }
        if ($page === 'images') {
            return ['images.find' => '[data-tour="images.find"]', 'images.records' => '[data-tour="images.records"]', 'images.inspector' => '[data-tour="images.inspector"]'];
        }

        return str_starts_with($page, 'catalog-') && config('guided-tours.screens.'.$page.'.scope') === null
            ? ['screen.heading' => '.catalog-page-header', 'screen.workspace' => '#catalog-content > [data-tour="catalog.workspace"]']
            : ['screen.heading' => '.fi-header', 'screen.workspace' => '.fi-page-content'];
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

        $steps = is_array($input['steps'] ?? null) ? array_values($input['steps']) : [];
        $stage = $input['stage'] ?? ($steps[0]['page'] ?? 'images');
        $input['stage'] = $stage;
        if (is_string($stage) && is_array($input['steps'] ?? null)) {
            foreach ($input['steps'] as &$step) {
                if (is_array($step)) {
                    $step['page'] = $stage;
                }
            }
            unset($step);
        }

        return DB::transaction(function () use ($record, $input, $stage): TourDefinition {
            $tour = $record?->exists ? TourDefinition::query()->lockForUpdate()->findOrFail($record->id) : new TourDefinition;
            $data = Validator::make($input, [
                'key' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('tour_definitions')->where('locale', $input['locale'] ?? '')->ignore($tour)],
                'title' => ['required', 'string', 'max:255'],
                'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/'],
                'enabled' => ['required', 'boolean'],
                'stage' => ['required', 'string', Rule::in(array_keys(self::pages()))],
                'steps' => ['required', 'array', 'min:1', 'max:30'],
                'steps.*' => ['required', 'array:key,page,target,title,description,placement'],
                'steps.*.key' => ['required', 'string', 'alpha_dash:ascii', 'max:100', 'distinct'],
                'steps.*.page' => ['required', Rule::in(array_keys(self::pages()))],
                'steps.*.target' => ['required', Rule::in(array_keys(self::targets(is_string($stage) ? $stage : '')))],
                'steps.*.title' => ['required', 'string', 'max:255'],
                'steps.*.description' => ['required', 'string', 'max:2000'],
                'steps.*.placement' => ['required', Rule::in(array_keys(self::placements()))],
            ])->validate();
            $data['steps'] = array_values($data['steps']);
            unset($data['stage']);
            $tour->fill($data);
            $tour->revision = $tour->exists ? $tour->revision + 1 : 1;
            $tour->save();

            return $tour;
        });
    }

    /** @return list<array<string, mixed>> */
    public function forPage(?User $actor, string $page, ?string $locale = null): array
    {
        if ($actor === null || ! Gate::forUser($actor)->allows('manage-catalog') || ! array_key_exists($page, self::pages()) || ($page === 'images' && ! config('catalog-assets.enabled')) || ($page === 'writing' && ! config('configurator-writing.enabled')) || ! Schema::hasTable('tour_definitions')) {
            return [];
        }
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');
        $headers = TourDefinition::query()->where('enabled', true)->where('steps->[0]->page', $page)->whereIn('locale', [$locale, $fallback])
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

    /** @return list<array<string, mixed>> */
    public static function starters(): array
    {
        $starters = [self::starter()];
        foreach (config('guided-tours.screens', []) as $page => $screen) {
            if ($page === 'images') {
                continue;
            }
            $starters[] = ['key' => 'screen-'.$page, 'title' => $screen['title'], 'locale' => 'en', 'enabled' => true, 'steps' => [
                ['key' => 'overview', 'page' => $page, 'target' => 'screen.heading', 'title' => $screen['title'], 'description' => $screen['intro'], 'placement' => 'bottom'],
                ['key' => 'workspace', 'page' => $page, 'target' => 'screen.workspace', 'title' => 'Working here', 'description' => $screen['workspace'], 'placement' => 'top'],
            ]];
        }

        return $starters;
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
