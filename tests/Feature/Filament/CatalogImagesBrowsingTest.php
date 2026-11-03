<?php

use App\Filament\Pages\CatalogImages;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('images browse is gated and exposes only saved images without catalog writes', function () {
    $product = Product::factory()->create(['product_code' => 'D60-EL-LP6-01']);
    $before = $product->fresh()->getAttributes();
    Livewire::test(CatalogImages::class)->assertCanSeeTableRecords([$product])
        ->call('inspectProductImage', $product->id, imagesContext())
        ->assertSet('imageRecordId', $product->id)->assertSee('No main image')
        ->assertDontSee('Save & next')->assertDontSee('Keeps your position when the page size changes.');
    expect($product->fresh()->getAttributes())->toBe($before);
    config(['catalog-assets.enabled' => false]);
    Livewire::test(CatalogImages::class)->assertForbidden();
});

test('results checks and inspected record have independent lifetimes and cross page navigation', function () {
    $group = Group::factory()->create();
    $products = collect(range(1, 12))->map(fn ($n) => Product::factory()->for($group)->create(['product_code' => sprintf('D60-EL-%02d', $n)]));
    $page = Livewire::test(CatalogImages::class)
        ->call('checkImagePage', imagesContext())->assertCount('selectedTableRecords', 10);
    $page->call('inspectProductImage', $products[9]->id, $page->instance()->imageBrowserContext())->assertSet('imageRecordId', $products[9]->id);
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext())
        ->assertSet('imageRecordId', $products[10]->id)->assertSet('paginators.page', 2)
        ->set('imageSearch.query', 'nothing')->assertSet('imageRecordId', $products[10]->id)
        ->assertCount('selectedTableRecords', 10);
    expect($page->instance()->imageCheckedCounts())->toBe(['inside' => 0, 'outside' => 10]);
    $page->call('setImageNavigationMode', 'checked', $page->instance()->imageBrowserContext());
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext())
        ->assertSet('imageRecordId', $products[0]->id);
});

test('entity changes clear transient checks and reject old same id inspection commands', function () {
    $group = Group::factory()->create(['id' => 40]);
    Product::factory()->for($group)->create(['id' => 40]);
    $page = Livewire::test(CatalogImages::class)->set('selectedTableRecords', [40]);
    $old = $page->instance()->imageBrowserContext();
    $page->call('switchImageTarget', 'group.main_image', $old)->assertSet('selectedTableRecords', [])->assertSet('imageRecordId', null)
        ->call('inspectProductImage', 40, $old)->assertHasErrors('imageBrowser')->assertSet('imageRecordId', null);
    $page->call('inspectGroupImage', 40, $page->instance()->imageBrowserContext())->assertSet('imageRecordId', 40);
});

test('custom pagination preserves ordinal and rejects raw invalid inputs before mutation', function () {
    $group = Group::factory()->create();
    Product::factory()->count(111)->for($group)->create();
    $page = Livewire::test(CatalogImages::class)->call('setPage', 11, 'page');
    $page->call('setImagePageSize', '25', $page->instance()->imageBrowserContext())
        ->assertSet('tableRecordsPerPage', 25)->assertSet('paginators.page', 5);
    foreach (['1.5', ' 20 ', '501', 'all', '0'] as $invalid) {
        $page->call('setImagePageSize', $invalid, $page->instance()->imageBrowserContext())->assertHasErrors('imagePageSize')->assertSet('tableRecordsPerPage', 25);
    }
    $page->set('tableRecordsPerPage', 'all');
    expect($page->instance()->getTableRecordsPerPage())->toBe(10);
});

test('profile restoration is scoped all or nothing and does not retain checks or exact codes', function () {
    $page = Livewire::test(CatalogImages::class);
    $profile = $page->instance()->imageBrowserProfile();
    $profile['perPage'] = 13;
    $profile['search']['query'] = 'el 6';
    $page->call('restoreTableBrowserProfile', $profile)->assertSet('tableRecordsPerPage', 13)->assertSet('imageSearch.query', 'el 6');
    $bad = $profile;
    $bad['perPage'] = 25;
    $bad['search']['query'] = 'changed';
    $bad['selectedTableRecords'] = [1];
    $page->call('restoreTableBrowserProfile', $bad)->assertHasErrors('profile')->assertSet('tableRecordsPerPage', 13)->assertSet('imageSearch.query', 'el 6');
});

test('exact codes transfer retries finalize idempotently without exposing contents and fail closed on cache loss', function () {
    $product = Product::factory()->create(['product_code' => 'Exact-001']);
    $other = Product::factory()->create(['product_code' => 'exact001']);
    $page = Livewire::test(CatalogImages::class)->call('beginExactCodePaste', imagesContext());
    $identity = $page->get('pendingCodePaste');
    $context = $page->instance()->imageBrowserContext();
    $page->call('appendExactCodePaste', $identity, 0, 'Exact-001', $context)->assertHasNoErrors()
        ->call('appendExactCodePaste', $identity, 0, 'Exact-001', $context)->assertHasNoErrors()
        ->call('appendExactCodePaste', $identity, 0, 'Conflict!', $context)->assertHasErrors('exactCodePaste');
    $page->call('finishExactCodePaste', $identity, $context)->assertHasNoErrors()
        ->assertCanSeeTableRecords([$product])->assertCanNotSeeTableRecords([$other]);
    $revision = $page->get('imageBrowserRevision');
    $page->call('finishExactCodePaste', $identity, $page->instance()->imageBrowserContext())->assertSet('imageBrowserRevision', $revision);
    expect(json_encode($page->get('exactCodeSet')))->not->toContain('Exact-001');
    expect(json_encode($page->snapshot['data'], JSON_THROW_ON_ERROR))->not->toContain('Exact-001');
    Cache::flush();
    $page->call('$refresh')->assertCanNotSeeTableRecords([$product, $other])->assertSee('Paste exact codes again');
});

function imagesContext(): array
{
    return ['target' => 'product.main_image', 'mode' => 'results', 'record' => null, 'revision' => 0];
}

test('pasted code finalization can recover its exact retry after the first response was lost', function () {
    Product::factory()->create(['product_code' => 'EL-001']);
    $page = Livewire::test(CatalogImages::class)->call('beginExactCodePaste', imagesContext());
    $identity = $page->get('pendingCodePaste');
    $page->call('appendExactCodePaste', $identity, 0, 'EL-001', imagesContext());
    $stale = clone $page->instance();
    $page->call('finishExactCodePaste', $identity, imagesContext());
    expect($stale->finishExactCodePaste($identity, imagesContext()))->toBe($identity);
    expect($stale->exactCodeSet)->toBe($identity)->and($stale->pendingCodePaste)->toBeNull();
});

test('superseded paste requests cannot resurrect an old session through an old snapshot', function () {
    $page = Livewire::test(CatalogImages::class)->call('beginExactCodePaste', imagesContext());
    $old = $page->get('pendingCodePaste');
    $stale = clone $page->instance();
    $page->call('beginExactCodePaste', imagesContext());
    expect(fn () => $stale->appendExactCodePaste($old, 0, 'EL-001', imagesContext()))->toThrow(ValidationException::class);
});

test('foreign host transport and expired sets cannot be used to broaden results', function () {
    $product = Product::factory()->create(['product_code' => 'EL-001']);
    $page = Livewire::test(CatalogImages::class)->call('beginExactCodePaste', imagesContext());
    $identity = $page->get('pendingCodePaste');
    Livewire::test(CatalogImages::class)->call('appendExactCodePaste', $identity, 0, 'EL-001', imagesContext())->assertHasErrors('exactCodePaste');
    $page->call('appendExactCodePaste', $identity, 0, 'EL-001', imagesContext())->call('finishExactCodePaste', $identity, imagesContext());
    $this->travel(31)->minutes();
    $page->call('$refresh')->assertCanNotSeeTableRecords([$product])->assertSee('Paste exact codes again');
});

test('the full paste byte boundary and code count boundary are accepted separately', function () {
    $page = Livewire::test(CatalogImages::class)->call('beginExactCodePaste', imagesContext());
    $identity = $page->get('pendingCodePaste');
    $raw = str_repeat('X', 524288);
    foreach (str_split($raw, 32768) as $offset => $chunk) {
        $page->call('appendExactCodePaste', $identity, $offset * 32768, $chunk, imagesContext())->assertHasNoErrors();
    }
    $page->call('appendExactCodePaste', $identity, 524288, 'X', imagesContext())->assertHasErrors('exactCodePaste')
        ->call('finishExactCodePaste', $identity, imagesContext())->assertHasNoErrors();
    expect(strlen(json_encode($page->get('exactCodeSet'))))->toBeLessThan(256);
    $page->call('beginExactCodePaste', $page->instance()->imageBrowserContext());
    $next = $page->get('pendingCodePaste');
    $codes = implode("\n", array_map(fn ($n) => 'EL-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), range(1, 5000)));
    $context = $page->instance()->imageBrowserContext();
    foreach (str_split($codes, 32768) as $offset => $chunk) {
        $page->call('appendExactCodePaste', $next, $offset * 32768, $chunk, $context);
    }
    $page->call('finishExactCodePaste', $next, $context)->assertHasNoErrors();
    $previous = $page->get('exactCodeSet');
    $page->call('beginExactCodePaste', $page->instance()->imageBrowserContext());
    $invalid = $page->get('pendingCodePaste');
    $context = $page->instance()->imageBrowserContext();
    $codes .= "\nEL-5001";
    foreach (str_split($codes, 32768) as $offset => $chunk) {
        $page->call('appendExactCodePaste', $invalid, $offset * 32768, $chunk, $context);
    }
    $page->call('finishExactCodePaste', $invalid, $context)->assertHasErrors('imageSearch.exact_codes')->assertSet('exactCodeSet', $previous);
});

test('check matches accepts exactly 5000 and rejects an oversized union without truncating existing checks', function () {
    $group = Group::factory()->create();
    foreach (array_chunk(range(1, 5001), 250) as $chunk) {
        $rows = array_map(fn ($n) => Product::factory()->for($group)->make(['legacy_id' => 'cap-'.$n, 'product_code' => 'CAP-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT)])->getAttributes(), $chunk);
        Product::query()->insert($rows);
    }
    $last = Product::where('product_code', 'CAP-5001')->firstOrFail();
    $page = Livewire::test(CatalogImages::class)->set('selectedTableRecords', [$last->id]);
    $page->call('checkImageMatches', $page->instance()->imageBrowserContext())->assertHasErrors('checks')->assertSet('selectedTableRecords', [$last->id]);
    $page->set('selectedTableRecords', [])->set('imageSearch.exclude', 'CAP-5001');
    $page->call('checkImageMatches', $page->instance()->imageBrowserContext())->assertCount('selectedTableRecords', 5000);
    $page->call('setImagePageSize', '500', $page->instance()->imageBrowserContext())->assertSet('tableRecordsPerPage', 500);
    expect($page->instance()->getTableRecords()->count())->toBe(500);
    expect(strlen(json_encode($page->snapshot, JSON_THROW_ON_ERROR)))->toBeLessThan(1048576);
    $page->set('selectedTableRecords', range(1, 5001))->assertHasErrors('checks')->assertCount('selectedTableRecords', 5000);
});

test('removed records do not cause silent navigation and search never changes the inspected identity', function () {
    $a = Product::factory()->create(['product_code' => 'A-1']);
    $b = Product::factory()->create(['product_code' => 'B-2']);
    $page = Livewire::test(CatalogImages::class)->call('inspectProductImage', $a->id, imagesContext());
    $a->delete();
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext())->assertHasErrors('imageBrowser')->assertSet('imageRecordId', $a->id);
    expect($b->fresh()->main_image_id)->toBeNull();
});

test('direct RPCs recheck access and Group labels and references are escaped as text', function () {
    $product = Product::factory()->create(['product_code' => 'Safe-1', 'product_name' => '<script>alert(1)</script>', 'profile_fields' => ['product_pic' => 'https://bad.test/<script>']]);
    $page = Livewire::test(CatalogImages::class)->call('inspectProductImage', $product->id, imagesContext());
    $page->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $page->set('imageSearch.targets', ['reference'])->set('imageSearch.query', 'bad.test')->assertCanSeeTableRecords([$product]);
    config(['catalog-assets.enabled' => false]);
    $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext())->assertForbidden();
    config(['catalog-assets.enabled' => true]);
    $this->actingAs(User::factory()->create(['email' => 'viewer@example.test']));
    Livewire::test(CatalogImages::class)->assertForbidden();
});

test('oversized restored profiles and unsupported sorts reject before partial state changes', function () {
    $page = Livewire::test(CatalogImages::class);
    $profile = $page->instance()->imageBrowserProfile();
    $profile['search']['query'] = str_repeat('x', 32768);
    $profile['perPage'] = 50;
    $page->call('restoreTableBrowserProfile', $profile)->assertHasErrors('bytes')->assertSet('tableRecordsPerPage', 10);
    $profile = $page->instance()->imageBrowserProfile();
    $profile['sort'] = 'group.name:asc';
    $profile['perPage'] = 50;
    $page->call('restoreTableBrowserProfile', $profile)->assertHasErrors('tableSort')->assertSet('tableRecordsPerPage', 10);
});

test('invalid live controls restore their prior valid state and native sorts cannot introduce SQL expressions', function () {
    Product::factory()->create(['product_code' => 'EL-1']);
    $page = Livewire::test(CatalogImages::class)->set('imageSearch.query', 'el');
    $page->set('imageSearch.targets', ['sql.raw'])->assertHasErrors('imageSearch.targets.0')->assertSet('imageSearch.targets', ['code'])->assertSet('imageSearch.query', 'el');
    $page->call('sortTable', 'group.name', 'asc')->assertHasErrors('tableSort')->assertSet('tableSort', null);
    $page->set('tableSort', 'id; DROP:asc')->assertHasErrors('tableSort')->assertSet('tableSort', null);
    $page->set('isTrackingDeselectedTableRecords', true)->assertHasErrors('checks')->assertSet('isTrackingDeselectedTableRecords', false);
});

test('rendered native controls retain their complete action bindings and compact pagination labels', function () {
    $product = Product::factory()->create(['product_code' => 'EL-001']);
    $page = Livewire::test(CatalogImages::class);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$page->html());
    $xpath = new DOMXPath($document);
    $actions = $xpath->query('//*[@*[name()="x-on:click"]]');
    $found = false;
    foreach ($actions as $action) {
        $value = $action->getAttribute('x-on:click');
        if (str_contains($value, 'catalog-images-inspect')) {
            expect($value)->toContain('inspectProductImage', 'product.main_image', '\\u0022record\\u0022:'.$product->id)->toEndWith(')');
            $found = true;
        }
    }
    expect($found)->toBeTrue();
    $select = $document->getElementById('images-page-size');
    expect($select->getAttribute('title'))->toBe('Rows per page')->and($select->getAttribute('aria-label'))->toBe('Rows per page');
});

test('failed transient cache writes keep the prior exact filter and allow finalization retry', function () {
    $a = Product::factory()->create(['product_code' => 'EL-001']);
    $b = Product::factory()->create(['product_code' => 'EL-002']);
    $page = Livewire::test(CatalogImages::class)->call('beginExactCodePaste', imagesContext());
    $first = $page->get('pendingCodePaste');
    $page->call('appendExactCodePaste', $first, 0, 'EL-001', imagesContext())->call('finishExactCodePaste', $first, imagesContext());
    $page->call('beginExactCodePaste', $page->instance()->imageBrowserContext());
    $next = $page->get('pendingCodePaste');
    $context = $page->instance()->imageBrowserContext();
    $page->call('appendExactCodePaste', $next, 0, 'EL-002', $context);
    $store = Cache::getStore();
    $mock = Mockery::mock($store)->makePartial();
    $attempts = 0;
    $mock->shouldReceive('put')->twice()->withArgs(fn ($key) => str_starts_with($key, 'catalog-images:paste-control:'))->andReturnUsing(function ($key, $value, $seconds) use ($store, &$attempts) {
        return ++$attempts === 1 ? false : $store->put($key, $value, $seconds);
    });
    Cache::swap(new Repository($mock));
    $page->call('finishExactCodePaste', $next, $context)->assertHasErrors('exactCodePaste')->assertSet('exactCodeSet', $first)
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    $page->call('finishExactCodePaste', $next, $context)->assertHasNoErrors()->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
});

test('rendered target and mode buttons execute their current typed navigation intent', function () {
    $product = Product::factory()->create(['product_code' => 'EL-001']);
    $page = Livewire::test(CatalogImages::class);
    $intent = catalogImagesRenderedIntent($page->html(), 'Groups');
    expect($intent)->toBe(['command', 'switchImageTarget', 'group.main_image']);
    $page->call($intent[1], $intent[2], $page->instance()->imageBrowserContext())->assertSet('imageTarget', 'group.main_image');
    $intent = catalogImagesRenderedIntent($page->html(), 'Products');
    $page->call($intent[1], $intent[2], $page->instance()->imageBrowserContext())->assertSet('imageTarget', 'product.main_image');
    $page->call('inspectProductImage', $product->id, $page->instance()->imageBrowserContext());
    $intent = catalogImagesRenderedIntent($page->html(), 'Checked');
    expect($intent)->toBe(['command', 'setImageNavigationMode', 'checked']);
    $page->call($intent[1], $intent[2], $page->instance()->imageBrowserContext())->assertSet('imageNavigationMode', 'checked');
});

test('both rendered record title and Inspect buttons open the typed image inspector without a table action fallback', function () {
    $group = Group::factory()->create(['name' => 'Binding Group']);
    $product = Product::factory()->for($group)->create(['product_code' => 'EL-001']);
    foreach (['EL-001', 'Inspect'] as $label) {
        $page = Livewire::test(CatalogImages::class);
        $intent = catalogImagesRenderedIntent($page->html(), $label);
        expect($intent[0])->toBe('dispatch')->and($intent[1])->toBe('catalog-images-inspect');
        $detail = $intent[2];
        $page->call($detail['method'], $detail['record'], $detail['context'])->assertSet('imageRecordId', $product->id);
    }
    $page = Livewire::test(CatalogImages::class)->call('switchImageTarget', 'group.main_image', imagesContext());
    foreach (['Binding Group', 'Inspect'] as $label) {
        $intent = catalogImagesRenderedIntent($page->html(), $label);
        expect($intent[2]['method'])->toBe('inspectGroupImage');
        $page->call($intent[2]['method'], $intent[2]['record'], $intent[2]['context'])->assertSet('imageRecordId', $group->id);
    }
});

function catalogImagesRenderedIntent(string $html, string $label): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $binding = null;
    foreach ($document->getElementsByTagName('button') as $button) {
        if (trim(preg_replace('/\s+/u', ' ', $button->textContent)) !== $label) {
            continue;
        }
        expect($button->hasAttribute('wire:click.prevent.stop'))->toBeFalse('This explicit image trigger must not silently fall back to an unconfigured mounted table action.');
        $binding = $button->getAttribute('x-on:click');
        break;
    }
    expect($binding)->toBeString()->not->toBe('');
    $javascript = <<<'JS'
const calls = [];
new Function('$dispatch', 'command', process.argv[1])((...args) => calls.push(['dispatch', ...args]), (...args) => calls.push(['command', ...args]));
process.stdout.write(JSON.stringify(calls[0]));
JS;
    $process = new Process(['node', '-e', $javascript, $binding]);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}
