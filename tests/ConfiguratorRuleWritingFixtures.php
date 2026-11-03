<?php

use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/ConfiguratorFixtures.php';

/** @return object{ids: array<string, list<int>>, dispatcher: mixed, contexts: array<int, array<string, mixed>>, actors: array<int, array<string, mixed>>, cleaned: bool} */
function writingBeginOwnedFixtures(): object
{
    expect(DB::transactionLevel())->toBe(0)->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
    if (Attribute::whereIn('key', ['A', 'B', 'C', 'failed_fixture_A', 'failed_fixture_B', 'failed_fixture_C'])->exists()
        || Option::whereIn('code', ['A0', 'A1', 'B0', 'B1', 'C0', 'C1', 'Z0', 'Z1'])->exists()) {
        throw new LogicException('Writing fixtures require their reviewed canonical key/code space to be free.');
    }

    $scope = (object) ['ids' => [], 'dispatcher' => Model::getEventDispatcher(), 'contexts' => [], 'actors' => [], 'cleaned' => false];
    foreach (['ycm@data4.work', 'beny.cr@gmail.com'] as $email) {
        $actors = User::whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();
        if ($actors->count() > 1 || ($actors->isNotEmpty() && $actors->first()->email !== $email)) {
            throw new LogicException('The Writing fixture actor has a conflicting identity.');
        }
        foreach ($actors as $actor) {
            $scope->actors[$actor->id] = $actor->getRawOriginal();
        }
    }
    Model::setEventDispatcher(clone $scope->dispatcher);
    foreach ([Configurator::class, Attribute::class, Option::class, Value::class, User::class, Group::class, Product::class] as $class) {
        $class::created(function (Model $record) use ($scope): void {
            $scope->ids[$record->getTable()][] = (int) $record->getKey();
        });
    }
    CatalogContextSettings::created(function (CatalogContextSettings $record) use ($scope): void {
        $scope->contexts[$record->id] = $record->fresh()->getRawOriginal();
    });

    return $scope;
}

function writingFixtureActor(string $email): User
{
    if (! in_array($email, ['ycm@data4.work', 'beny.cr@gmail.com'], true)) {
        throw new LogicException('Only the reviewed Writing fixture actor identities may be reused.');
    }
    $actor = User::where('email', $email)->first() ?? DB::transaction(fn (): User => User::factory()->create(['email' => $email]));
    Gate::forUser($actor)->authorize('manage-catalog');

    return $actor;
}

/** @return array{Configurator, array<string, mixed>} */
function writingCanonicalDefinitionFixture(): array
{
    return DB::transaction(fn (): array => canonicalDefinitionFixture());
}

/** @param object{ids: array<string, list<int>>, dispatcher: mixed, contexts: array<int, array<string, mixed>>, actors: array<int, array<string, mixed>>, cleaned: bool}|null $scope */
function writingCleanupOwnedFixtures(?object $scope): void
{
    if ($scope === null || $scope->cleaned) {
        return;
    }
    try {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if (DB::getDriverName() === 'mysql') {
            DB::purge('mysql');
        }
        $owners = $scope->ids['configurators'] ?? [];
        DB::table('products')->whereIn('id', $scope->ids['products'] ?? [])->delete();
        DB::table('groups')->whereIn('id', $scope->ids['groups'] ?? [])->delete();
        DB::table('configurator_rule_writing_drafts')->whereIn('configurator_id', $owners)->delete();
        DB::table('configurator_rules')->whereIn('configurator_id', $owners)->delete();
        $includes = DB::table('configurator_attributes')->whereIn('configurator_id', $owners);
        (clone $includes)->update(['default_configurator_option_id' => null]);
        DB::table('configurator_options')->whereIn('configurator_attribute_id', (clone $includes)->select('id'))->delete();
        $includes->delete();
        DB::table('configurators')->whereIn('id', $owners)->delete();
        foreach (['options', 'attributes', 'values', 'users'] as $table) {
            DB::table($table)->whereIn('id', $scope->ids[$table] ?? [])->delete();
        }
        foreach ($scope->contexts as $id => $original) {
            $current = CatalogContextSettings::find($id);
            if ($current !== null) {
                expect($current->getRawOriginal())->toBe($original);
                DB::table('catalog_context_settings')->where('id', $id)->delete();
            }
        }
        foreach ($scope->actors as $id => $original) {
            expect(User::findOrFail($id)->getRawOriginal())->toBe($original);
        }
        expect(DB::transactionLevel())->toBe(0)->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
        $scope->cleaned = true;
    } finally {
        Model::setEventDispatcher($scope->dispatcher);
    }
}
