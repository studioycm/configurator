<?php

namespace App\Actions;

use App\Models\AdminAppearanceSettings;
use App\Models\User;
use App\Services\AdminAppearance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SaveAdminAppearanceSettings
{
    /** @param array<string, mixed> $settings */
    public function handle(User $actor, array $settings): AdminAppearanceSettings
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! Schema::hasTable('admin_appearance_settings')) {
            throw ValidationException::withMessages(['settings' => 'Shared appearance storage is not installed yet. Apply the reviewed Admin Appearance migration before saving. Your preview is still available.']);
        }
        $validated = app(AdminAppearance::class)->validate($settings);
        $record = DB::transaction(function () use ($validated): AdminAppearanceSettings {
            $record = AdminAppearanceSettings::whereKey(1)->lockForUpdate()->firstOrFail();
            if ($record->settings != $validated) {
                $record->fill(['settings' => $validated, 'version' => $record->version + 1])->save();
                DB::afterCommit(fn () => Cache::forget(AdminAppearance::CACHE_KEY));
            }

            return $record;
        });
        app(AdminAppearance::class)->rememberSaved($record->settings);

        return $record;
    }
}
