<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

class ItemCountColumn
{
    public static function make(string $name, string $label, string $listKey): TextColumn
    {
        return TextColumn::make($name)->label($label)->numeric()->toggleable()
            ->extraCellAttributes(fn (Model $record): array => ['x-data' => 'itemCountPreview('.Js::from($listKey).', '.$record->getKey().')', 'x-on:mouseenter' => 'show()', 'x-on:focusin' => 'show()', 'x-on:mouseleave' => 'hide()', 'x-on:focusout' => 'hide()'], merge: true)
            ->action(Action::make('items-'.$listKey)->label('View '.$label)->authorize('manage-catalog')->slideOver()->stickyModalHeader()->stickyModalFooter()->modalHeading($label)->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn (Model $record) => view('filament.resources.item-list-content', ['listKey' => $listKey, 'parentId' => $record->getKey(), 'allowLocalEditing' => true])));
    }
}
