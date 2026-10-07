<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class FormHints
{
    public static function make(string $text): Action
    {
        return Action::make('help')->label('Help: '.$text)
            ->icon(Heroicon::OutlinedQuestionMarkCircle)->iconButton()->color('gray')
            ->tooltip($text)->alpineClickHandler('$el._tippy?.show()')
            ->extraAttributes(['class' => 'catalog-help-trigger']);
    }
}
