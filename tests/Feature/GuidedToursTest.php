<?php

use App\Filament\Pages\AdminAppearance;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\TourDefinitions\Pages\ManageTourDefinitions;
use App\Models\TourDefinition;
use App\Models\User;
use App\Services\GuidedTours;
use Database\Seeders\TourDefinitionSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function tourInput(): array
{
    return ['key' => 'images-basics', 'title' => 'Images introduction', 'locale' => 'en', 'enabled' => true, 'stage' => 'images',
        'steps' => [
            ['key' => 'find', 'page' => 'images', 'target' => 'images.find', 'title' => 'Find records', 'description' => 'Search products or groups.', 'placement' => 'bottom'],
            ['key' => 'inspect', 'page' => 'images', 'target' => 'images.records', 'title' => 'Inspect a record', 'description' => 'Inspect does not select a record for assignment.', 'placement' => 'top'],
        ]];
}

test('the native tour manager creates and edits ordered steps without exposing revisions as input', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $input = tourInput();

    Livewire::test(ManageTourDefinitions::class)->callAction(CreateAction::class, data: $input)->assertHasNoFormErrors();
    $tour = TourDefinition::query()->sole();
    expect($tour->steps)->toEqual($input['steps']);
    expect($tour->revision)->toBe(1);
    $input['steps'] = array_reverse($input['steps']);
    $input['title'] = 'Reordered introduction';
    Livewire::test(ManageTourDefinitions::class)->callAction(TestAction::make(EditAction::class)->table($tour), data: $input)->assertHasNoFormErrors();

    expect($tour->fresh()->steps)->toEqual($input['steps']);
    expect($tour->fresh()->revision)->toBe(2);
});

test('each save advances the current revision and a fresh read bypasses cached old content', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    config(['catalog-assets.enabled' => true]);
    $service = app(GuidedTours::class);
    $tour = $service->save($actor, null, tourInput());
    $stale = $tour->fresh();
    $first = $service->forPage($actor, 'images', 'en');
    $next = tourInput();
    $next['steps'][0]['description'] = 'Updated explanation';
    $service->save($actor, $tour, $next);
    $next['steps'] = array_reverse($next['steps']);
    $saved = $service->save($actor, $stale, $next);

    expect($saved->revision)->toBe(3);
    expect($first[0]['steps'][0]['description'])->toBe('Search products or groups.');
    expect($service->forPage($actor, 'images', 'en')[0]['steps'])->toBe($next['steps']);
    expect(Cache::has('guided-tour:'.$tour->id.':1'))->toBeTrue();
});

test('native create and edit show service validation beside the actual modal fields', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $input = tourInput();
    $input['locale'] = 'invalid';
    Livewire::test(ManageTourDefinitions::class)->callAction(CreateAction::class, data: $input)
        ->assertHasFormErrors(['locale' => __('validation.regex', ['attribute' => 'locale'])]);
    expect(TourDefinition::count())->toBe(0);

    $tour = app(GuidedTours::class)->save($actor, null, tourInput());
    $input = tourInput();
    $input['locale'] = 'invalid';
    Livewire::test(ManageTourDefinitions::class)->callAction(TestAction::make(EditAction::class)->table($tour), data: $input)
        ->assertHasFormErrors(['locale' => __('validation.regex', ['attribute' => 'locale'])]);
    expect($tour->fresh()->revision)->toBe(1);
    expect($tour->fresh()->locale)->toBe('en');
});

test('duplicate step errors map back to the native repeater item identities', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $input = tourInput();
    $input['steps'][1]['key'] = 'find';
    $input['steps'] = [(string) Str::uuid() => $input['steps'][0], (string) Str::uuid() => $input['steps'][1]];
    $component = Livewire::test(ManageTourDefinitions::class)->mountAction(CreateAction::class)->set('mountedActions.0.data', $input);
    $keys = array_keys($component->get('mountedActions.0.data.steps'));
    $component->callMountedAction();
    $component->assertHasFormErrors([
        'steps.'.$keys[0].'.key',
        'steps.'.$keys[1].'.key',
    ]);
    expect(TourDefinition::count())->toBe(0);
});

test('cached definitions avoid reloading JSON while current enabled and locale metadata stay authoritative', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    config(['catalog-assets.enabled' => true]);
    $service = app(GuidedTours::class);
    $en = $service->save($actor, null, tourInput());
    $he = tourInput();
    $he['locale'] = 'he';
    $he['title'] = 'הדרכה';
    $he = $service->save($actor, null, $he);
    expect($service->forPage($actor, 'images', 'he')[0]['title'])->toBe('הדרכה');
    DB::enableQueryLog();
    DB::flushQueryLog();
    $service->forPage($actor, 'images', 'he');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(collect($queries)->filter(fn (array $q): bool => str_contains(Str::before($q['query'], ' from '), '"steps"'))->count())->toBe(0);
    $disabled = tourInput();
    $disabled['locale'] = 'he';
    $disabled['enabled'] = false;
    $service->save($actor, $he, $disabled);
    expect($service->forPage($actor, 'images', 'he')[0]['title'])->toBe($en->title);
    $en->delete();
    expect($service->forPage($actor, 'images', 'he'))->toBe([]);
});

test('tour management and delivery retain existing admin authorization and Images availability', function () {
    $visitor = User::factory()->create();
    $this->actingAs($visitor);
    Livewire::test(ManageTourDefinitions::class)->assertForbidden();
    expect(fn () => app(GuidedTours::class)->save($visitor, null, tourInput()))->toThrow(AuthorizationException::class);
    expect(app(GuidedTours::class)->forPage($visitor, 'images', 'en'))->toBe([]);
    expect(TourDefinition::count())->toBe(0);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    app(GuidedTours::class)->save($actor, null, tourInput());
    config(['catalog-assets.enabled' => false]);
    expect(app(GuidedTours::class)->forPage($actor, 'images', 'en'))->toBe([]);
});

test('unknown targets duplicate step keys and executable extra fields are rejected without creating content', function (string $change) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $input = tourInput();
    if ($change === 'target') {
        $input['steps'][0]['target'] = '#arbitrary-selector';
    }
    if ($change === 'duplicate') {
        $input['steps'][1]['key'] = 'find';
    }
    if ($change === 'callback') {
        $input['steps'][0]['onNextClick'] = 'alert(1)';
    }
    expect(fn () => app(GuidedTours::class)->save($actor, null, $input))->toThrow(ValidationException::class);
    expect(TourDefinition::count())->toBe(0);
})->with(['target', 'duplicate', 'callback']);

test('the starter tour installation is idempotent and preserves managed edits', function () {
    $this->seed(TourDefinitionSeeder::class);
    $tour = TourDefinition::query()->where('key', 'images-basics')->sole();
    $tour->update(['title' => 'My edited tour', 'enabled' => false]);
    $this->seed(TourDefinitionSeeder::class);
    expect(TourDefinition::count())->toBe(count(GuidedTours::pages()));
    expect($tour->fresh()->title)->toBe('My edited tour');
    expect($tour->fresh()->enabled)->toBeFalse();
});

test('a managed stage keeps its steps in one context and rejects highlights from another workspace', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $input = tourInput();
    $input['stage'] = 'groups-edit';
    $input['steps'] = [array_replace($input['steps'][0], ['target' => 'screen.workspace'])];
    Livewire::test(ManageTourDefinitions::class)->callAction(CreateAction::class, data: $input)->assertHasNoFormErrors();
    $tour = TourDefinition::query()->sole();
    expect($tour->stage)->toBe('groups-edit');
    expect($tour->steps[0]['page'])->toBe('groups-edit');
    expect(app(GuidedTours::class)->forPage($actor, 'groups-edit', 'en'))->toHaveCount(1);
    expect(app(GuidedTours::class)->forPage($actor, 'images', 'en'))->toBe([]);

    $input['steps'][0]['target'] = 'images.find';
    expect(fn () => app(GuidedTours::class)->save($actor, $tour, $input))->toThrow(ValidationException::class);
    expect($tour->fresh()->revision)->toBe(1);
});

test('stage installation creates screen-specific starters without replacing edited or disabled stages', function () {
    $this->seed(TourDefinitionSeeder::class);
    $group = TourDefinition::query()->where('key', 'screen-groups-edit')->sole();
    $group->update(['title' => 'Our group editing guide', 'enabled' => false]);
    $this->seed(TourDefinitionSeeder::class);

    expect(TourDefinition::query()->where('key', 'screen-groups-edit')->sole()->title)->toBe('Our group editing guide');
    expect($group->fresh()->enabled)->toBeFalse();
    expect(TourDefinition::query()->where('key', 'screen-writing')->sole()->steps[1]['description'])->toContain('Save');
    expect(TourDefinition::query()->where('key', 'screen-catalog-products')->exists())->toBeTrue();
});

test('the native tour manager filters stages by their screen and hydrates existing context on edit', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $this->seed(TourDefinitionSeeder::class);
    $group = TourDefinition::query()->where('key', 'screen-groups-edit')->sole();
    $images = TourDefinition::query()->where('key', 'images-basics')->sole();

    Livewire::test(ManageTourDefinitions::class)->filterTable('stage', 'groups-edit')
        ->assertCanSeeTableRecords([$group])->assertCanNotSeeTableRecords([$images])
        ->mountAction(TestAction::make(EditAction::class)->table($group))
        ->assertSet('mountedActions.0.data.stage', 'groups-edit');
});

test('the shared page hook offers only the current workspace stage on render and subsequent requests', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $this->seed(TourDefinitionSeeder::class);
    config(['catalog-assets.enabled' => false]);

    $this->get(route('filament.admin.pages.admin-appearance'))->assertSee('Show me around')->assertSee('screen-appearance', false);
    Livewire::test(AdminAppearance::class)->call('$refresh')->assertSee('Show me around')->assertSee('screen-appearance', false);
    expect(GuidedTours::pageForScopes([EditGroup::class]))->toBe('groups-edit');
    expect(GuidedTours::pageForScopes(['unregistered']))->toBeNull();
    expect(app(GuidedTours::class)->forPage($actor, 'images', 'en'))->toBe([]);
    expect(app(GuidedTours::class)->forPage($actor, 'appearance', 'en'))->toHaveCount(1);
});
