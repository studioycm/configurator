<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

class FormOrderControls
{
    public static function make(string $name, ?Closure $itemLabel = null): Repeater
    {
        return Repeater::make($name)->reorderableWithButtons()->reorderableWithDragAndDrop()
            ->itemLabel($itemLabel)
            ->extraFieldWrapperAttributes(fn (Repeater $component): array => ['x-data' => 'localOrderProfile('.Js::from(['user' => auth()->id(), 'key' => $component->getStatePath()]).')'])
            ->moveUpAction(fn (Action $action, Repeater $component): Action => self::contextualAction($action, $component, $itemLabel, 'move_up')->extraAttributes(['x-show' => 'showButtonOrder', 'x-cloak' => true], merge: true))
            ->moveDownAction(fn (Action $action, Repeater $component): Action => self::contextualAction($action, $component, $itemLabel, 'move_down')->extraAttributes(['x-show' => 'showButtonOrder', 'x-cloak' => true], merge: true))
            ->deleteAction(fn (Action $action, Repeater $component): Action => self::contextualAction($action, $component, $itemLabel, 'remove'))
            ->reorderAction(fn (Action $action): Action => $action->extraAttributes(['x-show' => 'showDragOrder', 'x-cloak' => true], merge: true))
            ->hintActions([
                Action::make('toggleOrderButtons')->label(__('admin.ordering.labels.show_or_hide_updown_controls'))->iconButton()->icon(Heroicon::OutlinedArrowsUpDown)
                    ->tooltip(__('admin.ordering.help.show_or_hide_updown_controls'))->alpineClickHandler('showButtonOrder = ! showButtonOrder')->extraAttributes(['x-bind:aria-pressed' => 'showButtonOrder']),
                Action::make('toggleOrderDrag')->label(__('admin.ordering.labels.show_or_hide_drag_handles'))->iconButton()->icon(Heroicon::OutlinedBars3)
                    ->tooltip(__('admin.ordering.help.show_or_hide_drag_handles_order_is'))->alpineClickHandler('showDragOrder = ! showDragOrder')->extraAttributes(['x-bind:aria-pressed' => 'showDragOrder']),
            ]);
    }

    private static function contextualAction(Action $action, Repeater $component, ?Closure $itemLabel, string $operation): Action
    {
        if ($itemLabel !== null) {
            $action->label(fn (array $arguments): string => __('admin-ux.preset_'.$operation, ['name' => $itemLabel($component->getRawState()[$arguments['item']] ?? [], (string) $arguments['item'])]));
        }

        return $action;
    }
}
