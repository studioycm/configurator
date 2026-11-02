<?php

namespace App\Filament\Resources;

use App\Livewire\Catalog\AssetAssignment;
use App\Models\CatalogAssetBatchItem;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class CatalogAssetReviewTable
{
    public static function proposedUrl(CatalogAssetBatchItem $row): ?string
    {
        $choice = $row->snapshot['chosen_entry'] ?? null;

        return ! is_array($choice) ? null : URL::temporarySignedRoute('catalog.images.source-preview', now()->addMinutes(10), ['source' => $choice['source_id'], 'version' => $choice['scan_version'], 'entry' => $choice['entry_id']], false);
    }

    public static function configure(Table $table, AssetAssignment $page): Table
    {
        return $table->queryStringIdentifier('catalog-image-review')->query(fn (): Builder => $page->reviewRows())
            ->columns([
                TextColumn::make('position')->label('#')->formatStateUsing(fn (int $state): int => $state + 1),
                ImageColumn::make('saved')->label('Saved')->state(fn (CatalogAssetBatchItem $record): ?string => $page->savedReviewImages()[$record->before_asset_id]['thumbnail_url'] ?? null)->imageSize(44)->extraImgAttributes(['style' => 'object-fit:contain', 'alt' => '']),
                TextColumn::make('snapshot.title')->label('Record')->limit(60),
                TextColumn::make('snapshot.raw_display')->label('Raw field')->limit(80)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('snapshot.identifier')->label('Identifier')->limit(80),
                ImageColumn::make('proposed')->label('Proposed')->state(fn (CatalogAssetBatchItem $record): ?string => self::proposedUrl($record))->imageSize(44)->extraImgAttributes(['style' => 'object-fit:contain', 'alt' => '']),
                TextColumn::make('snapshot.filename')->label('Filename')->limit(80),
                TextColumn::make('match_status')->label('Proposal')->badge(),
                IconColumn::make('included')->label('Included')->boolean(),
                TextColumn::make('selection')->label('Review choice')->state(fn (CatalogAssetBatchItem $record): string => $page->reviewSelectionLabel($record)),
                TextColumn::make('snapshot.reason')->label('Reason')->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', $state ?? ''))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('match_status')->label('Proposal status')->options(array_combine(['ready', 'existing', 'missing', 'ambiguous', 'invalid', 'suggestion'], ['Ready', 'Existing kept', 'Missing', 'Ambiguous', 'Invalid', 'Suggestion'])),
                TernaryFilter::make('included')->label('Included in review'),
            ])
            ->recordActions([
                Action::make('Inspect')->authorize(fn (): bool => $page->canReview())->alpineClickHandler(fn (CatalogAssetBatchItem $record): string => '$dispatch(\'image-review-command\', '.Js::from(['method' => 'inspectReviewItem', 'args' => [$record->id]])->toHtml().')'),
                Action::make('Include')->visible(fn (CatalogAssetBatchItem $record): bool => ($page->reviewProgress['stage'] ?? '') === 'review' && ! $record->included && $record->match_status === 'ready')->authorize(fn (): bool => $page->canReview())
                    ->alpineClickHandler(fn (CatalogAssetBatchItem $record): string => '$dispatch(\'image-review-command\', '.Js::from(['method' => 'setReviewIncluded', 'args' => [$record->id, true]])->toHtml().')'),
                Action::make('Exclude')->visible(fn (CatalogAssetBatchItem $record): bool => ($page->reviewProgress['stage'] ?? '') === 'review' && $record->included)->authorize(fn (): bool => $page->canReview())
                    ->alpineClickHandler(fn (CatalogAssetBatchItem $record): string => '$dispatch(\'image-review-command\', '.Js::from(['method' => 'setReviewIncluded', 'args' => [$record->id, false]])->toHtml().')'),
            ])->recordUrl(null)->recordAction(null)->searchable(false)->selectable(false)
            ->paginated(fn (): array => $page->reviewPageOptions())->defaultPaginationPageOption(10)->persistRecordsPerPageInSession(false)
            ->emptyStateHeading('No review rows')->emptyStateDescription('Preview the checked records, or adjust the review filters.');
    }
}
