<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ContextSettings;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Values\ValueResource;
use App\Services\AdminAppearance;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->favicon(asset('images/favicon-aquestia.png'))
            ->brandName('Aquestia')
            ->brandLogo(asset('images/logo-ari_dark.png'))
            ->brandLogoHeight(fn (): string => app(AdminAppearance::class)->current()['logo_height'].'px')
            ->sidebarWidth(fn (): string => app(AdminAppearance::class)->current()['sidebar_width'].'px')
            ->collapsedSidebarWidth(fn (): string => app(AdminAppearance::class)->current()['collapsed_sidebar_width'].'px')
            ->sidebarCollapsibleOnDesktop()
            ->darkModeBrandLogo(asset('images/logo-ari_dark.png'))
            ->defaultThemeMode(ThemeMode::Light)
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->topbar(false)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->navigationGroups([
                NavigationGroup::make()->label('Product configuration')->icon(Heroicon::OutlinedAdjustmentsHorizontal),
                NavigationGroup::make()->label('Catalog')->icon(Heroicon::OutlinedCube),
                NavigationGroup::make()->label('Settings')->icon(Heroicon::OutlinedCog6Tooth),
            ])
            ->login()
            ->passwordReset()
            ->colors([
                'primary' => '#0877e8',
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.resources.appearance-variables'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.resources.workspace-assets'))
            ->resources([GroupResource::class, ProductResource::class, ConfiguratorResource::class, AttributeResource::class, ValueResource::class, OptionResource::class])
            ->navigationItems([
                NavigationItem::make('Open dashboard catalog')
                    ->sort(0)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): string => route('catalog.index')),
            ])
            ->pages([
                Dashboard::class,
                ContextSettings::class,
                \App\Filament\Pages\AdminAppearance::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                SpatieTranslatablePlugin::make()
                    ->defaultLocales([config('app.locale')]),
            ]);
    }
}
