<?php

use App\Actions\DeleteCanonicalDefinition;
use App\Actions\SaveCanonicalDefinition;
use App\Actions\SaveCanonicalOption;
use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Options\Pages\CreateOption;
use App\Filament\Resources\Values\Pages\CreateValue;
use App\Filament\Resources\Values\ValueResource;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\CanonicalUsage;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

test('shared label edits preserve local overrides while used canonical identities and deletions require repair', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['attributes'][0]['label_override'] = 'Local label';
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $attribute = Attribute::findOrFail($data['attributes'][0]['attribute_id']);
    $option = Option::findOrFail($data['attributes'][0]['options'][0]['option_id']);
    app(SaveCanonicalDefinition::class)->handle($this->actor, $attribute, ['key' => $attribute->key, 'label' => 'Shared label']);
    expect(app(ConfiguratorDefinitionLoader::class)->load($configurator->id)->attributes[(string) $configurator->attributes()->where('attribute_id', $attribute->id)->sole()->id]->label)->toBe('Local label');
    expect(fn () => app(SaveCanonicalDefinition::class)->handle($this->actor, $attribute, ['key' => 'changed', 'label' => 'Changed']))->toThrow(ValidationException::class);
    foreach ([$attribute, $option, $option->value] as $record) {
        expect(fn () => app(DeleteCanonicalDefinition::class)->handle($this->actor, $record))->toThrow(ValidationException::class);
    }
    $usage = app(CanonicalUsage::class)->report($option);
    expect($usage['configurators'][0]['id'])->toBe($configurator->id)->and($usage['defaults'])->toHaveCount(1);
});

test('shared Values with equal labels remain separate meanings and unused rows can be deleted', function () {
    $first = app(SaveCanonicalDefinition::class)->handle($this->actor, new Value, ['label' => 'Equal label', 'description' => 'First meaning']);
    $second = app(SaveCanonicalDefinition::class)->handle($this->actor, new Value, ['label' => 'Equal label', 'description' => 'Second meaning']);
    expect($first->id)->not->toBe($second->id)->and(Value::count())->toBe(2);
    app(DeleteCanonicalDefinition::class)->handle($this->actor, $first);
    expect(Value::count())->toBe(1)->and($second->fresh()->description)->toBe('Second meaning');
});

test('all canonical mutations enforce the existing catalog gate', function () {
    $actor = User::factory()->create();
    $attribute = Attribute::factory()->create();
    expect(fn () => app(SaveCanonicalDefinition::class)->handle($actor, $attribute, ['key' => $attribute->key, 'label' => 'No']))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteCanonicalDefinition::class)->handle($actor, $attribute))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveCanonicalOption::class)->handle($actor, null, $attribute->id, Value::factory()->create()->id, '00'))->toThrow(AuthorizationException::class);
});

test('canonical forms create separate shared records and preserve exact manual Option codes', function () {
    Livewire\Livewire::test(CreateAttribute::class)
        ->fillForm(['key' => 'connection', 'label' => 'Connection'])->call('create')->assertHasNoFormErrors();
    $attribute = Attribute::where('key', 'connection')->sole();
    Livewire\Livewire::test(CreateValue::class)
        ->fillForm(['label' => 'Threaded', 'description' => 'A distinct meaning'])->call('create')->assertHasNoFormErrors();
    $value = Value::where('label', 'Threaded')->sole();
    Livewire\Livewire::test(CreateOption::class)
        ->fillForm(['attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => 'Aa'])->call('create')->assertHasNoFormErrors();
    expect(Option::sole()->code)->toBe('Aa');
    Livewire\Livewire::test(CreateOption::class)
        ->fillForm(['attribute_id' => $attribute->id, 'value_id' => Value::factory()->create()->id, 'code' => ' A'])->call('create')->assertHasFormErrors(['code']);
    expect(Option::count())->toBe(1);
});

test('canonical resources reject access outside the existing administrator gate', function () {
    $this->actingAs(User::factory()->create());
    foreach ([AttributeResource::class, ValueResource::class, OptionResource::class, ConfiguratorResource::class] as $resource) {
        expect($resource::canViewAny())->toBeFalse();
        $this->get($resource::getUrl())->assertForbidden();
    }
});

test('a concurrent canonical code collision becomes a validation error and rolls back the attempted save', function () {
    $attribute = Attribute::factory()->create();
    $value = Value::factory()->create();
    $other = Value::factory()->create();
    Option::creating(function (Option $option) use ($other): void {
        DB::table('options')->insert(['attribute_id' => $option->attribute_id, 'value_id' => $other->id, 'code' => $option->code]);
    });
    try {
        expect(fn () => app(SaveCanonicalOption::class)->handle($this->actor, null, $attribute->id, $value->id, 'Q0'))->toThrow(ValidationException::class);
    } finally {
        Option::flushEventListeners();
    }
    expect(Option::count())->toBe(0);
});
