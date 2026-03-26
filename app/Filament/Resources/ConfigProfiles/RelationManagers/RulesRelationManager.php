<?php

namespace App\Filament\Resources\ConfigProfiles\RelationManagers;

use App\Filament\Resources\OptionRules\Tables\AllowedOptionsTable;
use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\ConfigProfile;
use App\Models\OptionRule;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('option_attribute_id')
                    ->label('Attribute')
                    ->options(function (Get $get, Component $livewire) {
                        // 1. Try to get the ID from the form state (if we are in the standalone Resource)
                        $profileId = $get('config_profile_id');

                        // 2. Fallback to the Owner Record (if we are inside the Relation Manager)
                        if (empty($profileId) && method_exists($livewire, 'getOwnerRecord')) {
                            $profileId = $livewire->getOwnerRecord()->getKey();
                        }

                        return ConfigAttribute::query()
                            ->where('config_profile_id', $profileId)
                            ->pluck('label', 'id');
                    })
                    ->afterStateHydrated(function (Set $set, ?OptionRule $record) {
                        // Use ?OptionRule so it safely ignores this block on the Create screen
                        if ($record && $record->config_option_id) {
                            // Using the relation you defined in the OptionRule model
                            $set('option_attribute_id', $record->optionAttribute?->id ?? clone $record->option->config_attribute_id);
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('config_option_id', null))
                    ->dehydrated(false),
                Select::make('config_option_id')
                    ->label('Option')
                    ->disabled(fn (Get $get): bool => empty($get('option_attribute_id')))
                    ->relationship('option', 'label', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('config_attribute_id', $get('option_attribute_id')))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('target_attribute_id')
                    ->label('Target Attribute')
                    ->relationship('targetAttribute', 'label')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('allowed_option_ids', []))
                    ->required(),
                ModalTableSelect::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->multiple()
                    ->dehydrateStateUsing(fn (?array $state): array => array_values($state ?? []))
                    ->getOptionLabelsUsing(fn (?array $values): array =>
                    ConfigOption::query()
                        ->whereIn('id', $values ?? [])
                        ->pluck('label', 'id')
                        ->all()
                    )
                    ->disabled(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->hidden(fn (Get $get): bool => empty($get('target_attribute_id')))
                    ->selectAction(fn (Action $action) => $action->modalHeading('Select Allowed Options'))
                    ->tableArguments(fn (Get $get): array => [
                        'attribute_id' => $get('target_attribute_id'),
                    ])
                    ->tableConfiguration(AllowedOptionsTable::class),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn($query) => $query->with('configProfile', 'optionAttribute', 'option', 'targetAttribute'))
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('configProfile.name')
                    ->label('Configurator'),
                TextColumn::make('optionAttribute.label')
                    ->label('Attribute'),
                TextColumn::make('option.label')
                    ->label('Option'),
                TextColumn::make('targetAttribute.label')
                    ->label('Target Attribute'),
                TextColumn::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->state(fn (OptionRule $record) => collect($record->allowedOptionLabels())->join(', '))
                    ->limit(50)
                    ->tooltip(fn (OptionRule $record) => collect($record->allowedOptionLabels())->join(', '))
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
