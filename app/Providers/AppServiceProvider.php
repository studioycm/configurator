<?php

namespace App\Providers;

use App\Filament\Exports\ConfiguratorRuleExporter;
use App\Filament\Exports\Jobs\CreateConfiguratorRulesXlsxFile;
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
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->bind(CreateXlsxFile::class, function (Application $app, array $parameters): CreateXlsxFile {
            if ($parameters['export']->exporter === ConfiguratorRuleExporter::class) {
                return $app->make(CreateConfiguratorRulesXlsxFile::class, $parameters);
            }

            return new CreateXlsxFile($parameters['export'], $parameters['columnMap'], $parameters['options'] ?? []);
        });
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
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_AFTER, fn () => view('filament.resources.table-toolbar-tags'));
        Gate::define('manage-catalog', fn (User $user): bool => $user->canAccessPanel(Filament::getPanel('admin')));

        Tabs::configureUsing(function (Tabs $tabs): void {
            $tabs->extraAlpineAttributes(fn (Tabs $component): array => Filament::getCurrentPanel()?->getId() !== 'admin' || filled($component->getLivewireProperty()) ? [] : [
                'x-effect' => 'rememberSchemaState($el, tab, '.Js::from(['user' => auth()->id(), 'kind' => 'tab', 'key' => $component->getKey(), 'query' => $component->getTabQueryStringKey()]).')',
            ], merge: true);
        });
        Section::configureUsing(function (Section $section): void {
            $section->extraAlpineAttributes(fn (Section $component): array => $component->isCollapsible() && Filament::getCurrentPanel()?->getId() === 'admin' ? [
                'x-data' => 'localSectionProfile('.Js::from(['user' => auth()->id(), 'key' => $component->getKey()]).')',
            ] : [], merge: true);
        });

        Action::configureUsing(function (Action $action): void {
            $action->extraModalWindowAttributes(fn (Action $action): array => [
                'x-data' => 'workspaceDialog('.Js::from([
                    'user' => auth()->id() ?? 0,
                    'purpose' => hash('sha256', $action->getName()),
                    'slideOver' => $action->isModalSlideOver(),
                    'allowPersonalWidth' => ! $action->isConfirmationRequired(),
                    'icons' => [
                        'narrower' => svg('heroicon-o-arrows-pointing-in')->toHtml(),
                        'wider' => svg('heroicon-o-arrows-pointing-out')->toHtml(),
                        'reset' => svg('heroicon-o-arrow-path')->toHtml(),
                    ],
                    'labels' => [
                        'resize' => __('admin-ux.resize_drawer'),
                        'width' => __('admin.dialogs.width'),
                        'narrower' => __('admin.dialogs.narrower'),
                        'wider' => __('admin.dialogs.wider'),
                        'reset' => __('admin.dialogs.reset_width'),
                    ],
                    'width' => $action->isConfirmationRequired() ? 640 : (['small' => 640, 'medium' => 960, 'large' => 1280][app(AdminAppearance::class)->current()[$action->isModalSlideOver() ? 'slide_over_width' : 'modal_width']]),
                ]).')',
                'class' => 'catalog-workspace-dialog',
            ], merge: true);
        });
    }
}
