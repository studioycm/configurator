<?php

namespace App\Filament\Resources;

use App\Models\Attribute;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\Value;
use App\Services\CanonicalUsage;
use App\Services\InclusionUsage;
use Filament\Actions\Action;

class DependencyActions
{
    public static function canonical(Action $action, Attribute|Value|Option|null $record = null): Action
    {
        $resolveRecord = fn (): Attribute|Value|Option => $record ?? $action->getRecord();

        return $action->modalContent(fn () => view('filament.resources.canonical-usage', ['usage' => app(CanonicalUsage::class)->report($resolveRecord())]))
            ->modalSubmitAction(function (Action $action) use ($resolveRecord): Action {
                $record = $resolveRecord();

                return $action->disabled($record instanceof Option ? $record->inclusions()->exists() : ($record->options()->exists() || ($record instanceof Attribute && $record->inclusions()->exists())));
            })
            ->extraModalFooterActions(function () use ($resolveRecord): array {
                $record = $resolveRecord();
                $type = match (true) {
                    $record instanceof Attribute => 'attribute', $record instanceof Value => 'value', default => 'option'
                };
                $counts = app(CanonicalUsage::class)->report($record)['counts'];
                $actions = [];
                foreach ($counts as $category => $count) {
                    if ($count === 0 || ($record instanceof Option && $category === 'options')) {
                        continue;
                    }
                    $key = 'canonical-'.$type.'-'.$category;
                    $actions[] = Action::make('blocking-'.$category)->label('View '.$category.' ('.$count.')')->slideOver()->modalSubmitAction(false)->modalCancelActionLabel('Back')
                        ->modalContent(fn () => view('filament.resources.item-list-content', ['listKey' => $key, 'parentId' => $record->id]));
                }

                return $actions;
            });
    }

    public static function local(Action $action): Action
    {
        return $action->modalSubmitAction(function (Action $action, $record): Action {
            $usage = app(InclusionUsage::class);

            return $action->disabled($usage->rules($record)->exists() || ($record instanceof ConfiguratorOption && $usage->defaults($record)->exists()));
        })->extraModalFooterActions(function ($record): array {
            $usage = app(InclusionUsage::class);
            $keys = [$record instanceof ConfiguratorAttribute ? 'inclusion-blocking-rules' : 'local-option-blocking-rules' => $usage->rules($record)->count()];
            if ($record instanceof ConfiguratorOption) {
                $keys['local-option-defaults'] = $usage->defaults($record)->count();
            }
            $actions = [];
            foreach ($keys as $key => $count) {
                if ($count > 0) {
                    $actions[] = Action::make($key)->label((str_ends_with($key, 'defaults') ? 'View stored defaults' : 'View blocking rules').' ('.$count.')')->slideOver()->modalSubmitAction(false)->modalCancelActionLabel('Back')->modalContent(fn () => view('filament.resources.item-list-content', ['listKey' => $key, 'parentId' => $record->id]));
                }
            }

            return $actions;
        });
    }
}
