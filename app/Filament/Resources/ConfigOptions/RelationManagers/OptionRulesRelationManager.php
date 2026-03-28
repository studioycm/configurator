<?php

namespace App\Filament\Resources\ConfigOptions\RelationManagers;

use App\Filament\Resources\OptionRules\Tables\AllowedOptionsTable;
use App\Models\ConfigOption;
use App\Models\OptionRule;
use App\OptionRuleDependencyType;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OptionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('config_profile_id')
                    ->label('Configurator')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload()
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
                    ->getOptionLabelsUsing(fn (?array $values): array => ConfigOption::query()
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
                ToggleButtons::make('dependency_type')
                    ->label('Dependency Type')
                    ->options(OptionRuleDependencyType::class)
                    ->grouped()
                    ->default(OptionRuleDependencyType::Disabled->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['configProfile', 'optionAttribute', 'option', 'targetAttribute']))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('configProfile.name')
                    ->label('Configurator')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('optionAttribute.label')
                    ->label('Attribute')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('option.label')
                    ->label('Option')
                    ->description(fn (OptionRule $record): string => (string) ($record->option?->code))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('targetAttribute.label')
                    ->label('Target Attribute')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->state(fn (OptionRule $record): array => $record->allowedOptionLabels())
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->toggleable(),
                TextColumn::make('dependency_type')
                    ->label('Dependency Type')
                    ->badge()
                    ->formatStateUsing(fn (?OptionRuleDependencyType $state): string => $state?->getLabel() ?? 'Disabled')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('config_profile_id')
                    ->label('Configurator')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('option')
                    ->label('Option')
                    ->relationship('option', 'label')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('optionAttribute')
                    ->label('Attribute')
                    ->relationship('optionAttribute', 'label')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('target_attribute_id')
                    ->label('Target Attribute')
                    ->relationship('targetAttribute', 'label')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(5)
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
