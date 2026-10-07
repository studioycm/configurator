<?php

namespace App\Livewire\Catalog;

use App\Models\Product;
use App\Services\CatalogAvailability;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.catalog')]
class ProductShow extends Component
{
    #[Locked]
    public int $productId;

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
    }

    public function render(): View
    {
        $product = Product::with('group')->findOrFail($this->productId);
        app(CatalogAvailability::class)->assertProduct($product);

        return view('livewire.catalog.product-show', compact('product'))->title($product->product_name ?: $product->product_code);
    }
}
