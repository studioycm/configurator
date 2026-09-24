<?php

namespace Tests\Fixtures\Legacy;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use LogicException;
use Tests\Fixtures\Legacy\Filament\Pages\ConfigEngineDemo;
use Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\CatalogGroupResource;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigAttributes\ConfigAttributeResource;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigOptions\ConfigOptionResource;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigProfiles\ConfigProfileResource;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationParts\ConfigurationPartResource;
use Tests\Fixtures\Legacy\Filament\Resources\ConfigurationSpecifications\ConfigurationSpecificationResource;
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\FileAttachmentResource;
use Tests\Fixtures\Legacy\Filament\Resources\OptionRules\OptionRuleResource;
use Tests\Fixtures\Legacy\Filament\Resources\Parts\PartResource;
use Tests\Fixtures\Legacy\Filament\Resources\ProductConfigurations\ProductConfigurationResource;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\ProductProfileResource;

trait LoadsLegacyFixtures
{
    public function setUpLoadsLegacyFixtures(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Legacy fixtures are restricted to the isolated in-memory test database.');
        }

        foreach (glob(__DIR__.'/Database/Migrations/*.php') as $migration) {
            (require $migration)->up();
        }

        View::addNamespace('legacy', __DIR__.'/resources/views');
        $resources = [
            CatalogGroupResource::class,
            ConfigAttributeResource::class,
            ConfigOptionResource::class,
            ConfigProfileResource::class,
            ConfigurationPartResource::class,
            ConfigurationSpecificationResource::class,
            FileAttachmentResource::class,
            OptionRuleResource::class,
            PartResource::class,
            ProductConfigurationResource::class,
            ProductProfileResource::class,
        ];
        $panel = Filament::getPanel('admin');
        $panel->resources($resources)->pages([ConfigEngineDemo::class]);
        $panel->register();

        Route::prefix($panel->getPath())->name('filament.admin.')
            ->middleware([...$panel->getMiddleware(), ...$panel->getAuthMiddleware()])
            ->group(function () use ($panel, $resources): void {
                foreach ($resources as $resource) {
                    $resource::registerRoutes($panel);
                }
                Route::name('pages.')->group(fn () => ConfigEngineDemo::registerRoutes($panel));
            });
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
