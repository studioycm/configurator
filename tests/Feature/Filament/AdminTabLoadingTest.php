<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

test('inactive embedded editors do not fetch their record lists on initial Overview render', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    Livewire::test(EditConfigurator::class, ['record' => $configurator->id]);
    $lists = array_values(array_filter($queries, fn (string $sql): bool => preg_match('/select .* from "configurator_(rules|attributes|options)" where/i', $sql) === 1 && ! str_contains($sql, 'count(')));
    expect($lists)->toBeEmpty();
});
