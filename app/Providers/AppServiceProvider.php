<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
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
        Action::configureUsing(function (Action $action): void {
            $action->modalWidth(Width::SevenExtraLarge);
        }, isImportant: true);
    }
}
