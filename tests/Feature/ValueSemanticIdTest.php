<?php

use App\Actions\SaveCanonicalDefinition;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Options\Pages\EditOption;
use App\Filament\Resources\Values\Pages\CreateValue;
use App\Filament\Resources\Values\Pages\EditValue;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

test('equal Value names can have distinct semantic IDs and duplicate IDs are rejected', function () {
    $save = app(SaveCanonicalDefinition::class);
    $first = $save->handle($this->actor, new Value, ['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_body']);
    $second = $save->handle($this->actor, new Value, ['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_float']);

    expect($first->fresh()->semantic_id)->toBe('polypropylene_body')
        ->and($second->fresh()->semantic_id)->toBe('polypropylene_float');

    try {
        $save->handle($this->actor, $second, ['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_body']);
        $this->fail('A semantic ID already belonging to another Value was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('definition.semantic_id');
    }

    expect($second->fresh()->semantic_id)->toBe('polypropylene_float');
});

test('Value semantic IDs reject invalid author input', function (mixed $semanticId) {
    try {
        app(SaveCanonicalDefinition::class)->handle($this->actor, new Value, ['label' => 'Viton', 'semantic_id' => $semanticId]);
        $this->fail('An invalid semantic ID was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('definition.semantic_id');
    }

    expect(Value::count())->toBe(0);
})->with(['uppercase' => 'Viton_1', 'spaces' => 'viton 1', 'hyphens' => 'viton-1', 'empty' => '', 'null' => null, 'too long' => str_repeat('a', 101)]);

test('existing label-only callers get unique IDs and renaming a Value preserves its ID and Option code', function () {
    $save = app(SaveCanonicalDefinition::class);
    $first = $save->handle($this->actor, new Value, ['label' => 'Viton']);
    $second = $save->handle($this->actor, new Value, ['label' => 'Viton']);
    $option = Option::factory()->for($first)->create(['code' => 'Aa']);

    expect($first->semantic_id)->toBe('viton')->and($second->semantic_id)->toBe('viton_2');

    $save->handle($this->actor, $first, ['label' => 'Viton seal']);
    $save->handle($this->actor, $second, ['label' => 'Viton float', 'semantic_id' => 'viton_2']);

    expect($first->fresh()->semantic_id)->toBe('viton')
        ->and($second->fresh()->semantic_id)->toBe('viton_2')
        ->and($option->fresh()->value_id)->toBe($first->id)
        ->and($option->fresh()->code)->toBe('Aa');
});

test('concurrent Value semantic ID collisions produce a field error and roll back the save', function () {
    Value::creating(function (Value $value): void {
        DB::table('values')->insert(['label' => 'Concurrent Value', 'semantic_id' => $value->semantic_id]);
    });

    try {
        app(SaveCanonicalDefinition::class)->handle($this->actor, new Value, ['label' => 'Viton', 'semantic_id' => 'viton_1']);
        $this->fail('A concurrent semantic ID collision was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('semantic_id');
    } finally {
        Value::flushEventListeners();
    }

    expect(Value::count())->toBe(0);
});

test('Master Value forms suggest an ID once and preserve an author supplied ID', function () {
    Livewire::test(CreateValue::class)
        ->set('data.label', 'ASA 150')
        ->assertSet('data.semantic_id', 'asa_150')
        ->set('data.semantic_id', 'asa_150_eu')
        ->set('data.label', 'ASA 150 Europe')
        ->assertSet('data.semantic_id', 'asa_150_eu')
        ->call('create')->assertHasNoFormErrors();

    $value = Value::where('semantic_id', 'asa_150_eu')->sole();
    Livewire::test(EditValue::class, ['record' => $value->id])
        ->assertSchemaStateSet(['semantic_id' => 'asa_150_eu'])
        ->fillForm(['label' => 'ASA 150', 'semantic_id' => 'asa_150_eu'])
        ->call('save')->assertHasNoFormErrors();

    expect($value->fresh()->semantic_id)->toBe('asa_150_eu');
});

test('Master Value editing reports duplicate semantic IDs on the correct form field', function () {
    Value::factory()->create(['semantic_id' => 'viton_1']);
    $other = Value::factory()->create(['semantic_id' => 'viton_2']);

    Livewire::test(EditValue::class, ['record' => $other->id])
        ->fillForm(['semantic_id' => 'viton_1'])
        ->call('save')->assertHasFormErrors(['semantic_id']);

    expect($other->fresh()->semantic_id)->toBe('viton_2');
});

test('Attribute Options table distinguishes equal names and searches by semantic ID', function () {
    $attribute = Attribute::factory()->create();
    $body = Value::factory()->create(['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_body']);
    $float = Value::factory()->create(['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_float']);
    $bodyOption = Option::factory()->for($attribute)->for($body)->create();
    $floatOption = Option::factory()->for($attribute)->for($float)->create();

    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
        ->assertTableColumnExists('value.semantic_id', fn (TextColumn $column): bool => $column->getLabel() === 'ID Name' && $column->isGloballySearchable())
        ->assertSee('polypropylene_body')->assertSee('polypropylene_float')
        ->searchTable('polypropylene_float')
        ->assertCanSeeTableRecords([$floatOption])->assertCanNotSeeTableRecords([$bodyOption]);
});

test('both Attribute Value editors show and search the selected Master Value semantic ID', function () {
    $value = Value::factory()->create(['label' => 'Viton', 'semantic_id' => 'viton_1', 'description' => null]);
    Value::factory()->create(['label' => 'Viton', 'semantic_id' => 'viton_2']);
    $option = Option::factory()->for($value)->create();
    $page = Livewire::test(EditOption::class, ['record' => $option->id]);
    $field = $page->instance()->form->getFlatFields()['value_id'];

    expect($field->getOptionLabel())->toBe('Viton - viton_1')
        ->and($field->getSearchResults('viton_1'))->toBe([$value->id => 'Viton - viton_1']);

    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $option->attribute, 'pageClass' => EditAttribute::class])
        ->mountAction(TestAction::make('edit')->table($option));
    $field = $manager->instance()->getSchema($manager->instance()->getMountedActionSchemaName())->getFlatFields()['value_id'];

    expect($field->getOptionLabel())->toBe('Viton - viton_1')
        ->and($field->getSearchResults('viton_1'))->toBe([$value->id => 'Viton - viton_1']);
});

test('semantic ID migration backfills existing Values without changing their relations', function () {
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.semantic_id_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('semantic_id_migration_test');

    try {
        Schema::create('values', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->text('description')->nullable();
        });
        Schema::create('options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('value_id')->constrained('values');
            $table->char('code', 2);
        });
        $first = DB::table('values')->insertGetId(['label' => 'Viton', 'description' => 'First meaning']);
        $second = DB::table('values')->insertGetId(['label' => 'Viton', 'description' => 'Second meaning']);
        $long = DB::table('values')->insertGetId(['label' => str_repeat('a', 255)]);
        $emptySlug = DB::table('values')->insertGetId(['label' => '***']);
        $optionId = DB::table('options')->insertGetId(['value_id' => $second, 'code' => 'zZ']);

        $migration = require glob(database_path('migrations/*_add_semantic_id_to_values_table.php'))[0];
        $migration->up();

        expect(Value::findOrFail($first)->semantic_id)->toBe('viton')
            ->and(Value::findOrFail($second)->semantic_id)->toBe('viton_2')
            ->and(strlen(Value::findOrFail($long)->semantic_id))->toBeLessThanOrEqual(100)
            ->and(Value::findOrFail($emptySlug)->semantic_id)->toBe('value')
            ->and(Value::findOrFail($second)->description)->toBe('Second meaning')
            ->and(Option::findOrFail($optionId)->value_id)->toBe($second)
            ->and(Option::findOrFail($optionId)->code)->toBe('zZ');

        expect(fn () => DB::table('values')->insert(['label' => 'Another', 'semantic_id' => 'viton']))->toThrow(QueryException::class);
        expect(fn () => DB::table('values')->insert(['label' => 'Missing ID']))->toThrow(QueryException::class);

        $migration->down();
        expect(Schema::hasColumn('values', 'semantic_id'))->toBeFalse()
            ->and(DB::table('values')->count())->toBe(4)
            ->and(Option::findOrFail($optionId)->value_id)->toBe($second);
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('semantic_id_migration_test');
    }
});
