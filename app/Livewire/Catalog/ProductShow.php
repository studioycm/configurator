<?php

namespace App\Livewire\Catalog;

use App\Models\Product;
use App\Services\CatalogAvailability;
use App\Services\CatalogJourney;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.catalog')]
class ProductShow extends Component
{
    #[Locked]
    public int $productId;

    #[Locked]
    public ?string $journeyEnvelope = null;

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
        $raw = request()->query('r');
        $this->journeyEnvelope = $raw === null ? null : (is_string($raw) && strlen($raw) <= 8192 ? $raw : 'invalid');
    }

    public function render(): View
    {
        $product = Product::with('group')->findOrFail($this->productId);
        app(CatalogAvailability::class)->assertProduct($product);
        $journey = app(CatalogJourney::class);
        $resolved = $journey->resolve(auth()->user(), $this->journeyEnvelope, (int) $product->group_id, $product->id);
        $journeyNotice = $resolved['notice'];
        $catalogReturnUrl = $resolved['state'] === null ? route('catalog.index') : $journey->catalogUrl($resolved['state']);
        $groupReturnUrl = $resolved['state'] === null ? route('catalog.groups.show', $product->group_id) : $journey->groupUrl($resolved['state']);

        return view('livewire.catalog.product-show', compact('product', 'journeyNotice', 'catalogReturnUrl', 'groupReturnUrl'))->title($product->product_name !== '' ? $product->product_name : $product->product_code);
    }
}
