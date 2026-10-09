<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Support\Icons\Heroicon;

class FormOrderControls
{
    public static function make(string $name): Repeater
    {
        return Repeater::make($name)->reorderableWithButtons()->reorderableWithDragAndDrop()
            ->extraFieldWrapperAttributes(['x-data' => '{ showDragOrder: false, showButtonOrder: true }'])
            ->moveUpAction(fn (Action $action): Action => $action->extraAttributes(['x-show' => 'showButtonOrder', 'x-cloak' => true], merge: true))
            ->moveDownAction(fn (Action $action): Action => $action->extraAttributes(['x-show' => 'showButtonOrder', 'x-cloak' => true], merge: true))
            ->reorderAction(fn (Action $action): Action => $action->extraAttributes(['x-show' => 'showDragOrder', 'x-cloak' => true], merge: true))
            ->hintActions([
                Action::make('toggleOrderButtons')->label('Show or hide up/down controls')->iconButton()->icon(Heroicon::OutlinedArrowsUpDown)
                    ->tooltip('Show or hide up/down controls')->alpineClickHandler('showButtonOrder = ! showButtonOrder'),
                Action::make('toggleOrderDrag')->label('Show or hide drag handles')->iconButton()->icon(Heroicon::OutlinedBars3)
                    ->tooltip('Show or hide drag handles; order is saved with the form')->alpineClickHandler('showDragOrder = ! showDragOrder'),
            ]);
    }
}
