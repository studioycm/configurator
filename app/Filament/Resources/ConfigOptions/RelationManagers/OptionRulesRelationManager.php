<?php

namespace App\Filament\Resources\ConfigOptions\RelationManagers;

use App\Filament\Resources\OptionRules\Tables\AllowedOptionsTable;
use App\Models\ConfigOption;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OptionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('config_profile_id')
                    ->label('Config Profile')
                    ->relationship('configProfile', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('target_attribute_id')
                    ->label('Target Attribute')
                    ->relationship('targetAttribute', 'label')
                    ->searchable()
                    ->preload()
                    ->reactive()
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
            ->columns([
                TextColumn::make('configProfile.name')
                    ->label('Config Profile')
                    ->searchable(),
                TextColumn::make('targetAttribute.label')
                    ->label('Target Attribute')
                    ->searchable(),
                TextColumn::make('allowed_option_ids')
                    ->label('Allowed Options')
                    ->state(fn ($record) => collect($record->allowedOptionLabels())->join(', '))
                    ->limit(50)
                    ->tooltip(fn ($record) => collect($record->allowedOptionLabels())->join(', ')),
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
