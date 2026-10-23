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
    /**
     * @param  Closure(string, list<string>): string  $suggest
     * @param  Closure(Get): list<string>|null  $reservedIdentifiers
     * @return array<Hidden|TextInput>
     */
    public static function fields(TextInput $label, TextInput $identifier, Closure $suggest, ?Closure $reservedIdentifiers = null): array
    {
        $name = $identifier->getName();
        $used = '_suggested_'.$name;
        $intent = '_identifier_'.$name.'_intent';
        $candidateState = '_identifier_'.$name.'_candidate';
        $propose = function (Get $get, Set $set, Component $livewire, TextInput $field, bool $explicit = false) use ($name, $intent, $candidateState, $label, $suggest, $reservedIdentifiers): void {
            $source = (string) $get($label->getName());
            $candidate = $suggest($source, $reservedIdentifiers ? $reservedIdentifiers($get) : []);
            $set($candidateState, ['value' => $candidate, 'label' => $source, 'intent' => (string) $get($intent)]);
            $livewire->dispatch('identifier-suggested', field: $field->getContainer()->getStatePath().'.'.$name,
                livewireId: $livewire->getId(), rowRef: $get('ref'), label: $source,
                intent: (string) $get($intent), candidate: $candidate, explicit: $explicit);
        };

        return [
            Hidden::make($used)->default(false)->dehydrated(false),
            Hidden::make($intent)->default('')->dehydrated(false),
            Hidden::make($candidateState)->default(null)->dehydrated(false),
            $label->live(onBlur: true)
                ->extraInputAttributes(fn (TextInput $component): array => [
                    'x-on:input' => '$wire.$set('.Js::from($component->getContainer()->getStatePath().'.'.$intent).', window.nextIdentifierIntent(), false)',
                ], merge: true)
                ->afterStateUpdated(function (Get $get, Set $set, Component $livewire, TextInput $component, string $operation) use ($name, $used, $label, $propose): void {
                    if ($operation === 'create' && ! $get($used) && blank($get($name)) && filled($get($label->getName()))) {
                        $propose($get, $set, $livewire, $component);
                    }
                }),
            $identifier->live(onBlur: true)->afterStateUpdated(fn (Set $set) => $set($used, true))
                ->extraInputAttributes(['x-on:input' => 'identifierInput()'], merge: true)
                ->suffixIcon(fn (string $operation) => $operation === 'create' ? Heroicon::OutlinedCheck : null)->suffixIconColor('success')
                ->suffixAction(Action::make('regenerateIdentifier')->label(__('admin-ux.regenerate_identifier', ['label' => $label->getLabel()]))
                    ->tooltip(__('admin-ux.regenerate_identifier', ['label' => $label->getLabel()]))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->disabled(fn (Get $get): bool => blank($get($label->getName())))
                    ->extraAttributes(['x-on:click.capture' => 'identifierIntent()'])
                    ->action(function (Get $get, Set $set, Component $livewire, TextInput $component, string $operation) use ($propose): void {
                        Gate::authorize('manage-catalog');
                        abort_unless($operation === 'create', 403);
                        $propose($get, $set, $livewire, $component, true);
                    }))
                ->extraFieldWrapperAttributes(function (TextInput $component) use ($used, $intent, $label): array {
                    $root = $component->getContainer()->getStatePath();

                    return [
                        'class' => 'catalog-suggested-identifier',
                        'x-data' => 'suggestedIdentifier('.Js::from([
                            'field' => $component->getStatePath(), 'label' => $root.'.'.$label->getName(),
                            'used' => $root.'.'.$used, 'intent' => $root.'.'.$intent, 'ref' => $root.'.ref',
                        ]).')',
                        'x-bind:class' => "{ 'catalog-identifier-filled': suggested }",
                        'x-on:identifier-suggested.window' => 'receiveIdentifier($event.detail)',
                    ];
                }),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function reservation(array $row, string $identifier): ?string
    {
        if (filled($row[$identifier] ?? null)) {
            return (string) $row[$identifier];
        }
        $candidate = $row['_identifier_'.$identifier.'_candidate'] ?? null;
        if (($row['_suggested_'.$identifier] ?? false) || ! is_array($candidate)
            || ($candidate['label'] ?? null) !== ($row['label'] ?? null)
            || ($candidate['intent'] ?? '') !== (string) ($row['_identifier_'.$identifier.'_intent'] ?? '')) {
            return null;
        }

        return is_string($candidate['value'] ?? null) ? $candidate['value'] : null;
    }

    /** @param list<string> $reserved */
    public static function unique(string $label, Builder $query, string $column, array $reserved = []): string
    {
        $base = rtrim(substr(Str::slug($label, '_'), 0, 100), '_') ?: 'attribute';
        $identifier = $base;
        $suffix = 2;
        while (in_array($identifier, $reserved, true) || (clone $query)->where($column, $identifier)->exists()) {
            $ending = '_'.$suffix++;
            $identifier = rtrim(substr($base, 0, 100 - strlen($ending)), '_').$ending;
        }

        return $identifier;
    }
}
