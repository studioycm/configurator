<?php

use App\Models\Attribute;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Services\ItemListMembers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('membership distinguishes canonical Options from local inclusions with coincident identities', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $attribute = Attribute::factory()->create();
    $canonical = Option::factory()->for($attribute)->create(['id' => 50001]);
    $inclusion = ConfiguratorAttribute::factory()->create();
    $other = Option::factory()->for($inclusion->attribute)->create();
    $local = ConfiguratorOption::factory()->for($inclusion, 'configuratorAttribute')->for($other)->create(['id' => 50001]);
    $members = app(ItemListMembers::class);
    $canonicalQuery = $members->query($actor, 'attribute-options', $attribute->id);
    $localQuery = $members->query($actor, 'inclusion-options', $inclusion->id);
    expect($canonicalQuery->getModel())->toBeInstanceOf(Option::class);
    expect($canonicalQuery->pluck('id')->all())->toBe([$canonical->id]);
    expect($localQuery->getModel())->toBeInstanceOf(ConfiguratorOption::class);
    expect($localQuery->pluck('option_id')->all())->toBe([$other->id]);
    expect($members->query($actor, 'canonical-attribute-inclusions', $attribute->id)->pluck('id')->all())->toBe([]);
    expect($members->query($actor, 'option-inclusions', $other->id)->pluck('id')->all())->toBe([$local->id]);
});

test('membership denies unknown and deleted owners and keeps literal property lists separate from SQL', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $members = app(ItemListMembers::class);
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['0', '001', '1', 'literal.field']]]);
    expect($members->propertyKeys($actor, $group->id))->toBe(['0', '001', '1', 'literal.field']);
    expect($members->query($actor, 'group-products', $group->id)->pluck('id')->all())->toBe([]);
    expect(fn () => $members->query($actor, 'unknown', $group->id))->toThrow(HttpException::class);
    expect(fn () => $members->query($actor, 'group-card-properties', $group->id))->toThrow(HttpException::class);
    $id = $group->id;
    $group->delete();
    expect(fn () => $members->propertyKeys($actor, $id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $members->query($actor, 'group-products', $id))->toThrow(ModelNotFoundException::class);
    $outsider = User::factory()->create();
    expect(fn () => $members->query($outsider, 'value-options', 1))->toThrow(AuthorizationException::class);
    expect(fn () => $members->propertyKeys($outsider, 1))->toThrow(AuthorizationException::class);
});
