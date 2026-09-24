<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Values\ValueResource;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
            ->brandLogo(asset('images/logo-ari.png'))
            ->brandLogoHeight('2.5rem')
            ->sidebarWidth('16rem')
            ->darkModeBrandLogo(asset('images/logo-ari.png'))
            ->defaultThemeMode(ThemeMode::Light)
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->topbar(false)
            ->navigationGroups(['Product configuration', 'Catalog'])
//            ->sidebarCollapsibleOnDesktop(true)
            ->login()
            ->passwordReset()
            ->colors([
                'primary' => '#0877e8',
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->resources([GroupResource::class, ProductResource::class, ConfiguratorResource::class, AttributeResource::class, ValueResource::class, OptionResource::class])
            ->navigationItems([
                NavigationItem::make('Open dashboard catalog')
                    ->sort(0)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): string => route('catalog.index')),
            ])
            ->pages([
                Dashboard::class,
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
