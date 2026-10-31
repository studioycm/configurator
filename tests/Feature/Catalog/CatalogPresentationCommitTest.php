<?php

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveCatalogPresentationSettings;
use App\Actions\SaveGroupSettings;
use App\Filament\Pages\CatalogDisplay;
use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\User;
use App\Services\CatalogPresentation;
use App\Services\CatalogRevisions;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('native settings success state follows actual enclosing commit and leaves newer drafts intact', function (string $outcome) {
    expect(DB::transactionLevel())->toBe(0);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $page = Livewire::test(CatalogDisplay::class)->instance();
    $page->data['sidebar_mode'] = 'tags';
    $savedBase = $page->savedSharedData;
    $write = function () use ($page, $outcome, $savedBase): void {
        DB::transaction(function () use ($page, $outcome, $savedBase): void {
            $page->save();
            expect($page->displayVersion)->toBe('defaults:v1');
            expect($page->savedSharedData)->toBe($savedBase);
            expect($page->draftStatuses()['shared'])->toBeTrue();
            expect(session('filament.notifications', []))->toBeEmpty();
            if ($outcome === 'rollback') {
                throw new RuntimeException('native rollback');
            }
            if ($outcome === 'newer draft') {
                $page->data['tag_counts'] = false;
            }
        });
    };
    if ($outcome === 'rollback') {
        expect($write)->toThrow(RuntimeException::class, 'native rollback');
        $this->assertDatabaseCount('catalog_presentation_settings', 0);
    } else {
        $write();
        expect(CatalogPresentationSettings::query()->sole()->settings['sidebar_mode'])->toBe('tags');
    }
    if ($outcome === 'commit') {
        expect($page->displayVersion)->not->toBe('defaults:v1');
        expect($page->savedSharedData)->toBe($page->data);
        expect($page->draftStatuses()['shared'])->toBeFalse();
        expect(session('filament.notifications', []))->toHaveCount(1);
    } else {
        expect($page->displayVersion)->toBe('defaults:v1');
        expect($page->savedSharedData)->toBe($savedBase);
        expect($page->draftStatuses()['shared'])->toBeTrue();
        expect($page->data['sidebar_mode'])->toBe('tags');
        expect(session('filament.notifications', []))->toBeEmpty();
        if ($outcome === 'newer draft') {
            expect($page->data['tag_counts'])->toBeFalse();
        }
    }
    expect(DB::transactionLevel())->toBe(0);
})->with(['commit', 'rollback', 'newer draft']);

test('Group form completion cannot clean newer drafts or another selected Group', function (string $scope, string $outcome) {
    expect(DB::transactionLevel())->toBe(0);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create()->fresh();
    $other = Group::factory()->create();
    $values = GroupTagValue::factory()->count(2)->create();
    $page = Livewire::test(CatalogDisplay::class)->call('requestGroup', $group->id)->instance();
    if ($scope === 'presentation') {
        $page->groupData['use_group_image_placement'] = true;
        $page->groupData['group_image_placement'] = 'right';
    } else {
        $page->tagData['value_ids'] = [$values[0]->id];
    }
    $before = $group->getAttributes();
    $write = function () use ($page, $scope, $outcome, $other, $values): void {
        DB::transaction(function () use ($page, $scope, $outcome, $other, $values): void {
            $scope === 'presentation' ? $page->saveGroupPresentation() : $page->saveGroupTags();
            expect($page->draftStatuses()[$scope === 'presentation' ? 'group' : 'tags'])->toBeTrue();
            expect(session('filament.notifications', []))->toBeEmpty();
            if ($outcome === 'rollback') {
                throw new RuntimeException('Group draft rollback');
            }
            if ($outcome === 'newer draft') {
                if ($scope === 'presentation') {
                    $page->groupData['group_image_placement'] = 'above';
                } else {
                    $page->tagData['value_ids'] = [$values[1]->id];
                }
            }
            if ($outcome === 'other Group') {
                $page->requestGroup($other->id);
                $page->resolveGroupChange('discard');
            }
        });
    };
    if ($outcome === 'rollback') {
        expect($write)->toThrow(RuntimeException::class, 'Group draft rollback');
        expect($group->fresh()->getAttributes())->toBe($before);
        expect($group->tagValues()->count())->toBe(0);
    } else {
        $write();
        if ($scope === 'presentation') {
            expect($group->fresh()->result_settings['catalog_presentation']['group_image_placement'])->toBe('right');
        } else {
            expect($group->tagValues()->pluck('group_tag_values.id')->all())->toBe([$values[0]->id]);
        }
    }
    if ($outcome === 'newer draft') {
        expect($scope === 'presentation' ? $page->groupData['group_image_placement'] : $page->tagData['value_ids'])->toBe($scope === 'presentation' ? 'above' : [$values[1]->id]);
    }
    if ($outcome === 'other Group') {
        expect($page->selectedGroupId)->toBe($other->id);
    }
    expect($page->draftStatuses()[$scope === 'presentation' ? 'group' : 'tags'])->toBe(! in_array($outcome, ['commit', 'other Group'], true));
    expect(session('filament.notifications', []))->toHaveCount($outcome === 'commit' ? 1 : 0);
    expect(DB::transactionLevel())->toBe(0);
})->with(['presentation', 'tags'])->with(['rollback', 'newer draft', 'other Group', 'commit']);

test('Group presentation and core changes coalesce once and enclosing rollback restores both', function (bool $rollback) {
    expect(DB::transactionLevel())->toBe(0);
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create(['result_settings' => ['future' => false]])->fresh();
    $before = $group->getAttributes();
    $fingerprint = app(CatalogPresentation::class)->groupFingerprint($group);
    $save = fn () => DB::transaction(fn () => app(CatalogRevisions::class)->batch(function () use ($manager, $group, $fingerprint, $rollback): void {
        $updated = app(SaveCatalogGroup::class)->handle($manager, $group, ['name' => 'Combined metadata save']);
        app(SaveGroupSettings::class)->patchPresentation($manager, $updated, ['catalog_presentation' => ['group_card_content' => []]], [], $fingerprint);
        if ($rollback) {
            throw new RuntimeException('rollback Group presentation');
        }
    }));
    if ($rollback) {
        expect($save)->toThrow(RuntimeException::class, 'rollback Group presentation');
        expect($group->fresh()->getAttributes())->toBe($before);
    } else {
        $save();
        expect($group->fresh()->name)->toBe('Combined metadata save');
        expect((int) $group->fresh()->catalog_revision)->toBe($group->catalog_revision + 1);
        expect($group->fresh()->result_settings)->toBe(['future' => false, 'catalog_presentation' => ['group_card_content' => []]]);
    }
    expect(DB::transactionLevel())->toBe(0);
})->with([false, true]);

test('standalone presentation save commits before publishing and warm and cold readers agree', function () {
    expect(DB::transactionLevel())->toBe(0);
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $writer = new SaveCatalogPresentationSettings($service);
    expect($service->current()['version'])->toBe('defaults:v1');

    $saved = $writer->handle($manager, $service->defaults(), 'defaults:v1');

    $this->assertModelExists($saved);
    expect($service->current()['version'])->toBe($saved->version);
    $warm = $service->current();
    expect((new CatalogPresentation)->current())->toBe($warm);
    Cache::flush();
    expect((new CatalogPresentation)->current())->toBe($warm);
    expect(DB::transactionLevel())->toBe(0);
});

test('outer commit exposes transactional state without publishing before the enclosing commit', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $writer = new SaveCatalogPresentationSettings($service);
    $first = $writer->handle($manager, $service->defaults(), 'defaults:v1');
    $service->current();
    $writes = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
        $writes[] = $event->key;
    });
    $next = DB::transaction(function () use ($manager, $service, $writer, $first, &$writes) {
        $next = $writer->handle($manager, array_replace($service->defaults(), ['sidebar_mode' => 'tags']), $first->version);
        expect($service->current()['settings']['sidebar_mode'])->toBe('tags');
        expect((new CatalogPresentation)->current()['version'])->toBe($next->version);
        expect($writes)->toBeEmpty();

        return $next;
    });

    expect($writes)->toHaveCount(1);
    expect($next->fresh()->version)->toBe($next->version);
    expect($service->current()['version'])->toBe($next->version);
});

test('outer rollback discards first-save and changed-save state without publishing', function (bool $existing) {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $writer = new SaveCatalogPresentationSettings($service);
    if ($existing) {
        $writer->handle($manager, $service->defaults(), 'defaults:v1');
    }
    $before = $service->current();
    $writes = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
        $writes[] = $event->key;
    });
    expect(fn () => DB::transaction(function () use ($manager, $service, $writer, $before) {
        $saved = $writer->handle($manager, array_replace($service->defaults(), ['tag_counts' => false]), $before['version']);
        expect($service->current()['version'])->toBe($saved->version);
        expect($service->current()['settings']['tag_counts'])->toBeFalse();
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect($writes)->toBeEmpty();
    expect($service->current())->toBe($before);
    expect(CatalogPresentationSettings::query()->count())->toBe($existing ? 1 : 0);
    expect(DB::transactionLevel())->toBe(0);
})->with([false, true]);

test('failed reference validation rolls back singleton preparation without publication', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $writer = new SaveCatalogPresentationSettings($service);
    $writes = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
        $writes[] = $event->key;
    });

    expect(fn () => $writer->handle($manager, array_replace($service->defaults(), ['group_card_tag_categories' => [PHP_INT_MAX]]), 'defaults:v1'))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('catalog_presentation_settings', 0);
    expect($writes)->toBeEmpty();
    expect($service->current()['version'])->toBe('defaults:v1');
});

test('unchanged shared preferences ignore stored object key order without SQL writes or publication', function (string $emptyField) {
    $this->freezeTime();
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $categories = GroupTagCategory::factory()->count(2)->create();
    $service = new CatalogPresentation;
    $settings = array_replace($service->defaults(), [
        'tag_counts' => false,
        'group_card_content' => ['legacy_id', 'tags', 'description'],
        'group_card_tag_categories' => [$categories[1]->id, $categories[0]->id],
    ]);
    if ($emptyField !== '') {
        $settings[$emptyField] = [];
    }
    $record = CatalogPresentationSettings::factory()->create(['settings' => array_reverse($settings, true)]);
    $before = $record->fresh()->getAttributes();
    $this->travel(1)->minute();
    $writes = [];
    $publications = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications) {
        $publications[] = $event->key;
    });

    $saved = (new SaveCatalogPresentationSettings($service))->handle($manager, $settings, $record->version);

    expect($writes)->toBeEmpty();
    expect($record->fresh()->getAttributes())->toBe($before);
    expect($saved->version)->toBe($record->version);
    expect($saved->settings['tag_counts'])->toBeFalse();
    expect($saved->settings['group_card_content'])->toBe($settings['group_card_content']);
    expect($saved->settings['group_card_tag_categories'])->toBe($settings['group_card_tag_categories']);
    expect($publications)->toBeEmpty();
    expect(DB::transactionLevel())->toBe(0);
})->with([
    'both ordered lists' => [''],
    'explicit empty content' => ['group_card_content'],
    'explicit empty categories' => ['group_card_tag_categories'],
]);

test('ordered preference changes still advance display version and publish after commit', function (string $field) {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $categories = GroupTagCategory::factory()->count(2)->create();
    $service = new CatalogPresentation;
    $settings = array_replace($service->defaults(), [
        'tag_counts' => false, 'group_card_content' => ['tags', 'description'],
        'group_card_tag_categories' => [$categories[1]->id, $categories[0]->id],
    ]);
    $record = CatalogPresentationSettings::factory()->create(['settings' => array_reverse($settings, true)]);
    $settings[$field] = $field === 'tag_counts' ? true : array_reverse($settings[$field]);
    $publications = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications) {
        $publications[] = $event->key;
    });

    $saved = (new SaveCatalogPresentationSettings($service))->handle($manager, $settings, $record->version);

    expect($saved->fresh()->version)->not->toBe($record->version);
    expect($saved->fresh()->settings[$field])->toBe($settings[$field]);
    expect($publications)->toHaveCount(1);
    expect(DB::transactionLevel())->toBe(0);
})->with(['group_card_content', 'group_card_tag_categories', 'tag_counts']);

test('ordinary warm readers recover changed same-version payload without masking valid preferences or repairing storage', function () {
    expect(DB::transactionLevel())->toBe(0);
    $service = new CatalogPresentation;
    $record = CatalogPresentationSettings::factory()->create();
    expect($service->current()['settings']['sidebar_mode'])->toBe('tree');
    $changed = array_replace($service->defaults(), [
        'sidebar_mode' => 'tags', 'tag_counts' => false, 'group_card_content' => [],
        'tag_columns' => 'private-invalid-value',
    ]);
    DB::table('catalog_presentation_settings')->where('id', 1)->update(['settings' => json_encode($changed, JSON_THROW_ON_ERROR)]);
    $before = $record->fresh()->getAttributes();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    foreach ([$service->current(), (new CatalogPresentation)->current()] as $current) {
        expect($current['version'])->toBe($record->version);
        expect($current['settings']['sidebar_mode'])->toBe('tags');
        expect($current['settings']['tag_counts'])->toBeFalse();
        expect($current['settings']['group_card_content'])->toBe([]);
        expect($current['settings']['tag_columns'])->toBe(2);
        expect($current['recovery']['defaulted_fields'])->toBe(['tag_columns']);
        expect(json_encode($current['recovery']))->not->toContain('private-invalid-value');
    }
    expect($writes)->toBeEmpty();
    expect($record->fresh()->getAttributes())->toBe($before);
    expect(DB::transactionLevel())->toBe(0);
});

test('warm payload identity distinguishes a same-version integer to float corruption', function () {
    $service = new CatalogPresentation;
    $record = CatalogPresentationSettings::factory()->create();
    expect($service->current()['recovery'])->toBeNull();
    $changed = array_replace($service->defaults(), ['tag_columns' => 2.0]);
    DB::table('catalog_presentation_settings')->where('id', 1)->update([
        'settings' => json_encode($changed, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    ]);
    $before = $record->fresh()->getAttributes();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    foreach ([$service->current(), (new CatalogPresentation)->current()] as $current) {
        expect($current['version'])->toBe($record->version);
        expect($current['settings']['tag_columns'])->toBe(2);
        expect($current['recovery']['defaulted_fields'])->toBe(['tag_columns']);
    }
    expect($record->fresh()->getAttributes())->toBe($before);
    expect($writes)->toBeEmpty();
    expect(DB::transactionLevel())->toBe(0);
});

test('after-commit cache identity survives stored top-level object reordering without another publication', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $saved = (new SaveCatalogPresentationSettings($service))->handle($manager, $service->defaults(), 'defaults:v1');
    DB::table('catalog_presentation_settings')->where('id', 1)->update([
        'settings' => json_encode(array_reverse($saved->settings, true), JSON_THROW_ON_ERROR),
    ]);
    $publications = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications) {
        $publications[] = $event->key;
    });

    foreach ([$service->current(), (new CatalogPresentation)->current()] as $current) {
        expect($current['version'])->toBe($saved->version);
        expect($current['settings'])->toBe($service->defaults());
        expect($current['recovery'])->toBeNull();
    }
    expect($publications)->toBeEmpty();
    expect(DB::transactionLevel())->toBe(0);
});
