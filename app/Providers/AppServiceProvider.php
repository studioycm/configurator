<?php

namespace App\Providers;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-catalog', fn (User $user): bool => $user->canAccessPanel(Filament::getPanel('admin')));

        Action::configureUsing(function (Action $action): void {
            $action->modalWidth(Width::SevenExtraLarge);
        }, isImportant: true);
    }
}
