<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

trait InteractsWithHeaderPresentation
{
    /** @return array<Action|ActionGroup> */
    public function getCachedHeaderActions(): array
    {
        $actions = parent::getCachedHeaderActions();
        $previous = null;
        foreach ($actions as $action) {
            if (! $action instanceof Action || ! $action->isVisible()) {
                continue;
            }
            $name = $action->getName();
            $context = match ($name) {
                'usage', 'openCatalog', 'manageTags' => 'navigation',
                'import', 'export' => 'data',
                'remove', 'delete' => 'destructive',
                default => 'record',
            };
            $action->icon($action->getIcon() ?? match ($name) {
                'usage' => Heroicon::OutlinedEye,
                'openCatalog' => Heroicon::OutlinedArrowTopRightOnSquare,
                'manageTags' => Heroicon::OutlinedTag,
                'import' => Heroicon::OutlinedArrowDownTray,
                'export' => Heroicon::OutlinedArrowUpTray,
                'duplicate' => Heroicon::OutlinedDocumentDuplicate,
                'changeStatus' => Heroicon::OutlinedAdjustmentsHorizontal,
                'remove', 'delete' => Heroicon::OutlinedTrash,
                default => null,
            })->color($context === 'destructive' ? 'danger' : 'gray')->outlined()
                ->extraAttributes(['data-header-context' => $context, 'data-header-group-start' => $previous !== null && $previous !== $context ? 'true' : 'false'], merge: true);
            $previous = $context;
        }

        return $actions;
    }
}
