<?php

namespace App\Actions;

use App\Models\CatalogPresentationSettings;
use App\Models\GroupTagCategory;
use App\Models\User;
use App\Services\CatalogPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCatalogPresentationSettings
{
    public function __construct(private CatalogPresentation $presentation) {}

    /** @param array<string,mixed> $settings */
    public function handle(User $actor, array $settings, string $expectedVersion): CatalogPresentationSettings
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $validated = $this->presentation->validate($settings);

        return DB::transaction(function () use ($validated, $expectedVersion): CatalogPresentationSettings {
            $initialVersion = null;
            if (! CatalogPresentationSettings::query()->whereKey(1)->exists()) {
                $initialVersion = (string) Str::ulid();
                CatalogPresentationSettings::query()->toBase()->upsert([
                    'id' => 1, 'settings' => json_encode($validated, JSON_THROW_ON_ERROR),
                    'version' => $initialVersion, 'created_at' => now(), 'updated_at' => now(),
                ], ['id'], ['id']);
            }
            $record = CatalogPresentationSettings::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $created = $initialVersion !== null && hash_equals($initialVersion, $record->version);
            if (! hash_equals($created ? 'defaults:v1' : $record->version, $expectedVersion)) {
                throw ValidationException::withMessages(['settings' => 'Catalog display changed. Reload it before saving.']);
            }
            $categoryIds = $validated['group_card_tag_categories'];
            $categories = GroupTagCategory::query()->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get(['id']);
            if ($categories->count() !== count($categoryIds)) {
                throw ValidationException::withMessages(['settings.group_card_tag_categories' => 'A selected group tag category is no longer available.']);
            }
            $storedSettings = $record->settings;
            $comparedSettings = $validated;
            if (is_array($storedSettings)) {
                ksort($storedSettings);
            }
            ksort($comparedSettings);
            if (! $created && $storedSettings !== $comparedSettings) {
                $record->fill(['settings' => $validated, 'version' => (string) Str::ulid()])->save();
            }
            if ($created || $record->wasChanged()) {
                $this->presentation->rememberSaved($validated, $record->version);
            }

            return $record;
        }, attempts: 1);
    }
}
