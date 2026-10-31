<?php

use App\Actions\SaveCatalogPresentationSettings;
use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCards;
use App\Services\CatalogPolicy;
use App\Services\CatalogPresentation;
use App\Services\CatalogSnapshots;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(fn () => $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work'])));

function presentationDom(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    return new DOMXPath($dom);
}

function presentedCatalogCards(Group $group): array
{
    $snapshot = app(CatalogSnapshots::class)->get($group->id);

    return app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $snapshot->revision(), 'presentation-cards');
}

test('actual card chunks bind shared and own leaf image settings without ancestor inheritance', function (string $placement, string $scale) {
    CatalogPresentationSettings::factory()->create(['settings' => array_replace(app(CatalogPresentation::class)->defaults(), ['product_image_placement' => $placement, 'product_image_scale' => $scale])]);
    $ancestor = Group::factory()->create(['result_settings' => ['catalog_presentation' => ['product_image_placement' => $placement === 'right' ? 'left' : 'right', 'product_image_scale' => $scale === 'large' ? 'small' : 'large']]]);
    $group = Group::factory()->for($ancestor, 'parent')->create();
    $product = Product::factory()->for($group)->create(['product_code' => 'IMAGE-001']);
    $response = presentedCatalogCards($group);
    $html = implode('', $response['htmlChunks']);
    $xp = presentationDom($html);
    $link = $xp->query('//article/a')->item(0);
    expect($link->getAttribute('data-image-placement'))->toBe($placement)->and($link->getAttribute('data-image-scale'))->toBe($scale)
        ->and($link->getAttribute('href'))->toBe(route('catalog.products.show', $product->id))
        ->and($link->getAttribute('data-catalog-focus'))->toBe('product-'.$product->id)
        ->and($link->getAttribute(':href'))->toContain('productHref')
        ->and($xp->query('//article//a')->length)->toBe(1)
        ->and($xp->query('//article//*[contains(@class,"catalog-image-placeholder")]')->length)->toBe(1)
        ->and($xp->query('//article//img')->length)->toBe(0);
    expect($response['total'])->toBe(1)->and($html)->toContain('IMAGE-001');
    $ownPlacement = $placement === 'above' ? 'below' : 'above';
    $group->update(['result_settings' => ['catalog_presentation' => ['product_image_placement' => $ownPlacement, 'product_image_scale' => 'small']]]);
    $owned = presentationDom(implode('', presentedCatalogCards($group)['htmlChunks']))->query('//article/a')->item(0);
    expect($owned->getAttribute('data-image-placement'))->toBe($ownPlacement)->and($owned->getAttribute('data-image-scale'))->toBe('small');
})->with([['above', 'small'], ['below', 'medium'], ['left', 'large'], ['right', 'small']]);

test('standalone Product cards keep pure default presentation and exact facts without a settings query', function () {
    CatalogPresentationSettings::factory()->create(['settings' => array_replace(app(CatalogPresentation::class)->defaults(), ['product_image_placement' => 'right'])]);
    $group = Group::factory()->make(['id' => 10]);
    $product = Product::factory()->make(['id' => 20, 'profile_fields' => ['0' => '001']]);
    $fields = [['key' => '0', 'label' => 'Code']];
    $appearance = CatalogPolicy::resultSettings(null);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $html = view('components.catalog.product-card', compact('product', 'group', 'fields', 'appearance'))->render();
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
    $xp = presentationDom($html);
    expect($xp->query('//article/a')->item(0)->getAttribute('data-image-placement'))->toBe('left')
        ->and(trim($xp->evaluate('string(//dd)')))->toBe('001');
});

test('Product page and independent child bind shared page settings rather than raw Group overrides', function (string $layout, string $scale) {
    $saved = CatalogPresentationSettings::factory()->create(['settings' => array_replace(app(CatalogPresentation::class)->defaults(), ['product_page_layout' => $layout, 'product_page_image_scale' => $scale])]);
    $parent = Group::factory()->create(['result_settings' => ['catalog_presentation' => ['product_page_layout' => 'image_column', 'product_page_image_scale' => 'large']]]);
    $group = Group::factory()->for($parent, 'parent')->create(['result_settings' => ['catalog_presentation' => ['product_page_layout' => $layout === 'image_column' ? 'compact_header' : 'image_column', 'product_page_image_scale' => $scale === 'small' ? 'large' : 'small']]]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['product_pic' => '/unmanaged/family.jpg']]);
    $html = $this->get(route('catalog.products.show', $product))->assertOk()->getContent();
    $xp = presentationDom($html);
    $root = $xp->query('//*[@data-product-page-layout]')->item(0);
    expect($root)->not->toBeNull();
    expect($root->getAttribute('data-product-page-layout'))->toBe($layout)
        ->and($root->getAttribute('data-product-page-image-scale'))->toBe($scale)
        ->and($root->getAttribute('data-catalog-display-version'))->toBe($saved->version)
        ->and($xp->query('.//*[@role="tablist"]', $root)->length)->toBe(1)
        ->and($xp->query('.//img', $root)->length)->toBe(0)
        ->and($xp->query('.//*[contains(@class,"catalog-image-placeholder")]', $root)->length)->toBe(1);
    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])
        ->assertViewHas('pagePresentation', fn (array $value): bool => $value === ['layout' => $layout, 'scale' => $scale, 'displayVersion' => $saved->version]);
})->with([['compact_header', 'small'], ['image_column', 'medium'], ['compact_header', 'large']]);

test('new presentation keeps numeric public Group property order distinct from editor fields and retains Parts slots', function () {
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['other']]]);
    GroupFilter::factory()->for($group)->create(['property_key' => '0', 'label' => 'Numeric property', 'sort_order' => 2]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'zero', 'label' => 'First property', 'sort_order' => 1]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['0' => '001', 'zero' => '0', 'other' => 'OTHERS-KEPT'], 'parts' => ['Part1' => 'FIRST-PART', 'Part28' => 'LAST-PART']]);
    $xp = presentationDom($this->get(route('catalog.products.show', $product))->assertOk()->getContent());
    expect(trim($xp->evaluate('string(//section[@aria-label="Properties"]//dt[1])')))->toBe('First property')
        ->and(collect($xp->query('//section[@aria-label="Properties"]//dd'))->map(fn ($node): string => trim($node->textContent))->all())->toBe(['0', '001'])
        ->and($xp->evaluate('string(//section[@aria-label="Others"])'))->toContain('OTHERS-KEPT')
        ->and($xp->query('//section[@aria-label="Parts"]//dd')->length)->toBe(28)
        ->and(trim($xp->evaluate('string((//section[@aria-label="Parts"]//dd)[2])')))->toBe('—')
        ->and($xp->evaluate('string(//section[@aria-label="Parts"])'))->toContain('FIRST-PART', 'LAST-PART');
});

test('saved page presentation changes on fresh native renders without changing real choices code or Group revision', function () {
    [$configurator, $definition] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $definition);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create();
    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id]);
    foreach ($component->viewData('result')->definition->attributes as $attribute) {
        $component->call('selectOption', $attribute->id, array_values($attribute->options)[0]->id);
    }
    $before = $component->viewData('result');
    expect($before->configurationCode)->not->toBeNull();
    $revision = $group->fresh()->catalog_revision;
    $service = app(CatalogPresentation::class);
    $current = $service->current();
    $saved = app(SaveCatalogPresentationSettings::class)->handle(auth()->user(), array_replace($current['settings'], ['product_page_layout' => 'image_column', 'product_page_image_scale' => 'large']), $current['version']);
    $component->call('refreshDefinition')->assertViewHas('pagePresentation', fn (array $value): bool => $value === ['layout' => 'image_column', 'scale' => 'large', 'displayVersion' => $saved->version]);
    $after = $component->viewData('result');
    expect($after->configurationCode)->toBe($before->configurationCode)->and($after->selections)->toBe($before->selections)
        ->and($after->attributes)->toBe($before->attributes)->and($group->fresh()->catalog_revision)->toBe($revision);
    $xp = presentationDom($component->html());
    expect($xp->query('//*[@role="tablist"]')->length)->toBe(1)
        ->and($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," catalog-configuration-attribute ")]')->length)->toBe(3);
});
