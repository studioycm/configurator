<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Component;

class SuggestedIdentifier
{
    /** @return array<Hidden|TextInput> */
    public static function fields(TextInput $label, TextInput $identifier, Closure $suggest): array
    {
        $name = $identifier->getName();
        $used = '_suggested_'.$name;
        $fill = function (Get $get, Set $set, Component $livewire) use ($name, $used, $label, $suggest): void {
            $set($name, $suggest((string) $get($label->getName())));
            $set($used, true);
            $livewire->dispatch('identifier-suggested', field: $name, livewireId: $livewire->getId());
        };

        return [
            Hidden::make($used)->default(false)->dehydrated(false),
            $label->live(onBlur: true)->afterStateUpdated(function (Get $get, Set $set, Component $livewire, string $operation) use ($name, $used, $label, $fill): void {
                if ($operation === 'create' && ! $get($used) && blank($get($name)) && filled($get($label->getName()))) {
                    $fill($get, $set, $livewire);
                }
            }),
            $identifier->live(onBlur: true)->afterStateUpdated(fn (Set $set) => $set($used, true))
                ->suffixIcon(fn (string $operation) => $operation === 'create' ? Heroicon::OutlinedCheck : null)->suffixIconColor('success')
                ->suffixAction(Action::make('regenerateIdentifier')->label(__('admin-ux.regenerate_identifier', ['label' => $label->getLabel()]))
                    ->tooltip(__('admin-ux.regenerate_identifier', ['label' => $label->getLabel()]))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->disabled(fn (Get $get): bool => blank($get($label->getName())))
                    ->action(function (Get $get, Set $set, Component $livewire, string $operation) use ($fill): void {
                        Gate::authorize('manage-catalog');
                        abort_unless($operation === 'create', 403);
                        $fill($get, $set, $livewire);
                    }))
                ->extraFieldWrapperAttributes(fn (Component $livewire): array => [
                    'class' => 'catalog-suggested-identifier',
                    'x-data' => '{ suggested: false, timer: null }',
                    'x-bind:class' => "{ 'catalog-identifier-filled': suggested }",
                    'x-on:identifier-suggested.window' => 'if ($event.detail.field === '.Js::from($name).' && $event.detail.livewireId === '.Js::from($livewire->getId()).') { clearTimeout(timer); suggested = true; timer = setTimeout(() => suggested = false, 1200); }',
                ]),
        ];
    }

    public static function unique(string $label, Builder $query, string $column): string
    {
        $base = rtrim(substr(Str::slug($label, '_'), 0, 100), '_') ?: 'attribute';
        $identifier = $base;
        $suffix = 2;
        while ((clone $query)->where($column, $identifier)->exists()) {
            $ending = '_'.$suffix++;
            $identifier = rtrim(substr($base, 0, 100 - strlen($ending)), '_').$ending;
        }

        return $identifier;
    }
}
