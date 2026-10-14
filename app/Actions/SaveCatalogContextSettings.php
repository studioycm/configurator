<?php

namespace App\Actions;

use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\User;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveCatalogContextSettings
{
    public function __construct(private ConfiguratorDefinitionCompiler $compiler, private ConfiguratorDefinitionLoader $loader) {}

    /** @param array<string, mixed> $choices */
    public function handle(User $actor, array $choices): CatalogContextSettings
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $choices = $this->compiler->context($choices);

        return DB::transaction(function () use ($choices): CatalogContextSettings {
            $configurators = Configurator::orderBy('id')->lockForUpdate()->get();
            $settings = CatalogContextSettings::current(lock: true);
            foreach ($configurators as $configurator) {
                $draft = $this->loader->draft($configurator);
                [$attributes, $options] = $this->loader->canonical($draft);
                try {
                    $this->compiler->compile($draft, $attributes, $options, globalContext: $choices);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'context_schema' => __('configurator.context.validation.cannot_save_global_choices_while_needs', ['value1' => $configurator->name, 'value2' => collect($exception->errors())->flatten()->first()]),
                    ]);
                }
            }
            $settings->update(['choices' => $choices]);

            return $settings;
        });
    }
}
