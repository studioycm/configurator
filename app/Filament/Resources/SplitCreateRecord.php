<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

abstract class SplitCreateRecord extends CreateRecord
{
    protected string $view = 'filament.resources.record-editor';

    #[Locked]
    public bool $embedded = false;

    public function redirect(mixed $url, mixed $navigate = false): void
    {
        if (! $this->embedded) {
            parent::redirect($url, $navigate);

            return;
        }

        $this->dispatch('catalog-editor-saved');
        $this->dispatch('catalog-record-saved');
        $this->dispatch('catalog-record-created', resource: static::getResource(), record: (string) $this->getRecord()->getKey())
            ->to(static::getResource()::getPages()['index']->getPage());
    }

    public function canCreateAnother(): bool
    {
        return ! $this->embedded && parent::canCreateAnother();
    }

    protected function getCancelFormAction(): Action
    {
        if (! $this->embedded) {
            return parent::getCancelFormAction();
        }

        return Action::make('cancel')->label('Cancel')->color('gray')
            ->extraAttributes(['data-editor-transition' => true])
            ->action(fn () => $this->dispatch('catalog-create-cancelled', resource: static::getResource())
                ->to(static::getResource()::getPages()['index']->getPage()));
    }
}
