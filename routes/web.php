<?php

use App\Http\Controllers\CatalogAssetSourcePreviewController;
use App\Http\Controllers\CatalogDatasetController;
use App\Http\Controllers\CatalogMainImageUploadController;
use App\Livewire\Catalog\GroupShow;
use App\Livewire\Catalog\Index as CatalogIndex;
use App\Livewire\Catalog\ProductShow;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use App\Services\CatalogJourney;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::post('/catalog-images/native-upload', CatalogMainImageUploadController::class)->name('catalog.images.native-upload');
    Route::get('/catalog-images/source-preview/{source}/{version}/{entry}', CatalogAssetSourcePreviewController::class)->middleware('signed:relative')->name('catalog.images.source-preview');
    Route::redirect('/dashboard', '/dashboard/catalog')->name('dashboard');
    Route::livewire('/dashboard/catalog', CatalogIndex::class)->name('catalog.index');
    Route::get('/dashboard/catalog/groups/{group}/dataset', CatalogDatasetController::class)->whereNumber('group')->name('catalog.groups.dataset');
    Route::livewire('/dashboard/catalog/groups/{group}', GroupShow::class)->name('catalog.groups.show');
    Route::livewire('/dashboard/catalog/products/{product}', ProductShow::class)->name('catalog.products.show');
    Route::redirect('/catalog', '/dashboard/catalog');
    Route::get('/catalog/groups/{group}', function (string $group) {
        if (array_key_exists('g', request()->query())) {
            CatalogIndex::queryFromUrl(request()->query('g'), null, (string) request()->server('QUERY_STRING', ''));
        }

        return redirect()->route('catalog.groups.show', ['group' => $group, ...app(CatalogJourney::class)->compatibilityQuery(auth()->user(), request()->query(), 'group', (int) $group)]);
    })->whereNumber('group');
    Route::get('/catalog/products/{product}', fn (string $product) => redirect()->route('catalog.products.show', ['product' => $product,
        ...app(CatalogJourney::class)->compatibilityQuery(auth()->user(), request()->query(), 'product', (int) $product)]))->whereNumber('product');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
    Route::livewire('settings/password', Password::class)->name('user-password.edit');
    Route::livewire('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::livewire('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
