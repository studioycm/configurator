<?php

namespace App\Providers;

use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use App\Services\AdminAppearance;
use App\Services\AdminNavigationCounts;
use App\Services\CatalogRevisions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CatalogRevisions::class);
        $this->app->scoped(AdminAppearance::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Attribute::class, Value::class, Option::class, Configurator::class, Group::class, Product::class] as $model) {
            $invalidate = fn () => app(AdminNavigationCounts::class)->forget($model);
            $model::created($invalidate);
            $model::deleted($invalidate);
        }

        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_START, fn () => view('filament.resources.table-toolbar-heading'));
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_SEARCH_BEFORE, fn () => view('filament.resources.table-toolbar-search'));
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_END, fn () => view('filament.resources.table-toolbar-actions'));
        Gate::define('manage-catalog', fn (User $user): bool => $user->canAccessPanel(Filament::getPanel('admin')));

        Action::configureUsing(function (Action $action): void {
            $action->extraModalWindowAttributes(fn (Action $action): array => [
                'x-data' => 'workspaceDialog('.Js::from([
                    'user' => auth()->id() ?? 0,
                    'purpose' => hash('sha256', $action->getName()),
                    'slideOver' => $action->isModalSlideOver(),
                    'width' => $action->isConfirmationRequired() ? 640 : (['small' => 640, 'medium' => 960, 'large' => 1280][app(AdminAppearance::class)->current()[$action->isModalSlideOver() ? 'slide_over_width' : 'modal_width']]),
                ]).')',
                'class' => 'catalog-workspace-dialog',
            ], merge: true);
        });
    }
}
