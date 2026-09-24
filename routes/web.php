<?php

use App\Livewire\Catalog\GroupShow;
use App\Livewire\Catalog\Index as CatalogIndex;
use App\Livewire\Catalog\ProductShow;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('/dashboard', '/dashboard/catalog')->name('dashboard');
    Route::livewire('/dashboard/catalog', CatalogIndex::class)->name('catalog.index');
    Route::livewire('/dashboard/catalog/groups/{group}', GroupShow::class)->name('catalog.groups.show');
    Route::livewire('/dashboard/catalog/products/{product}', ProductShow::class)->name('catalog.products.show');
    Route::redirect('/catalog', '/dashboard/catalog');
    Route::get('/catalog/groups/{group}', fn (string $group) => redirect()->route('catalog.groups.show', ['group' => $group, ...request()->query()]));
    Route::get('/catalog/products/{product}', fn (string $product) => redirect()->route('catalog.products.show', ['product' => $product]));
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
