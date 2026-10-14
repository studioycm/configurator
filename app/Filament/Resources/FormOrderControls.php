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
                Action::make('toggleOrderButtons')->label(__('admin.ordering.labels.show_or_hide_updown_controls'))->iconButton()->icon(Heroicon::OutlinedArrowsUpDown)
                    ->tooltip(__('admin.ordering.help.show_or_hide_updown_controls'))->alpineClickHandler('showButtonOrder = ! showButtonOrder'),
                Action::make('toggleOrderDrag')->label(__('admin.ordering.labels.show_or_hide_drag_handles'))->iconButton()->icon(Heroicon::OutlinedBars3)
                    ->tooltip(__('admin.ordering.help.show_or_hide_drag_handles_order_is'))->alpineClickHandler('showDragOrder = ! showDragOrder'),
            ]);
    }
}
