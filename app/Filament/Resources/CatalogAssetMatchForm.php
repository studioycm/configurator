<?php

namespace App\Filament\Resources;

use App\Livewire\Catalog\AssetAssignment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CatalogAssetMatchForm
{
    public static function configure(Schema $schema, AssetAssignment $page): Schema
    {
        return $schema->columns(1)->statePath('recipeData')->components([
            Section::make('Image source')->extraAttributes(['data-tour' => 'images.source'])->columnSpanFull()->columns(2)->schema([
                Select::make('source_choice')->label('Folder or existing source')->searchable()->required()
                    ->options(fn (): array => $page->sourceOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $page->sourceOptions($search))
                    ->getOptionLabelUsing(fn (string $value): ?string => $page->sourceLabel($value)),
                ToggleButtons::make('mode')->label('Existing images')->options(['fill_missing' => 'Fill missing', 'replace_existing' => 'Replace existing'])->inline()->required()->live(),
                Toggle::make('recursive_scan')->label('Scan subfolders')->live(),
            ]),
            Section::make('Filename recipe')->extraAttributes(['data-tour' => 'images.recipe'])->visible(fn (): bool => $page->operation === 'folder_matches')->columnSpanFull()->columns(2)->schema([
                ToggleButtons::make('parameters.source_field')->label('Identifier from')->options(['profile_fields' => 'Product field', 'product_code' => 'Product code'])->inline()->required()->live(),
                Select::make('parameters.field_key')->label('Exact field key')->searchable()->live()->required()
                    ->visible(fn (): bool => ($page->recipeData['parameters']['source_field'] ?? '') === 'profile_fields')
                    ->options(fn (): array => $page->fieldOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $page->fieldOptions($search))
                    ->getOptionLabelUsing(fn (string $value): string => $value),
                TextInput::make('positions')->label('Code positions, in order')->placeholder('1, 2, 4')->maxLength(60)->live(onBlur: true)
                    ->visible(fn (): bool => ($page->recipeData['parameters']['source_field'] ?? '') === 'product_code'),
                ToggleButtons::make('parameters.comparison')->label('Compare filename stem')->options(['exact' => 'Exact', 'contains' => 'Contains · confirm suggestions'])->inline()->required()->live(),
                Section::make('Advanced recipe')->collapsible()->collapsed()->columnSpanFull()->columns(2)->schema([
                    TextInput::make('parameters.separator')->label('Join positions with')->maxLength(10)->live(onBlur: true),
                    TextInput::make('parameters.prefix')->label('Prefix')->maxLength(100)->live(onBlur: true),
                    TextInput::make('parameters.suffix')->label('Suffix')->maxLength(100)->live(onBlur: true),
                    Toggle::make('parameters.case_sensitive')->label('Case sensitive')->live(),
                    Toggle::make('parameters.ignore_separators')->label('Ignore spaces, hyphens and underscores')->live(),
                    Toggle::make('parameters.include_subfolders')->label('Match subfolder files')->live(),
                    TextInput::make('parameters.include_patterns')->label('Include filenames (* and ?, comma separated)')->maxLength(500)->live(onBlur: true),
                    TextInput::make('parameters.exclude_patterns')->label('Exclude filenames (wins over include)')->maxLength(500)->live(onBlur: true),
                ]),
            ]),
        ]);
    }

    public static function candidate(Schema $schema, AssetAssignment $page): Schema
    {
        return $schema->columns(1)->statePath('candidateData')->components([
            Select::make('source_choice')->label('Find in another existing source')->searchable()
                ->options(fn (): array => $page->sourceOptions())
                ->getSearchResultsUsing(fn (string $search): array => $page->sourceOptions($search))
                ->getOptionLabelUsing(fn (string $value): ?string => $page->sourceLabel($value)),
        ]);
    }
}
