<?php

namespace App\Filament\Exports\Jobs;

use App\Filament\Exports\CatalogExportFiles;
use App\Services\CatalogDataExchangePolicy;
use Filament\Actions\Exports\Jobs\ExportCompletion;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;

class CompleteCatalogExport extends ExportCompletion implements ShouldQueueAfterCommit
{
    public function handle(): void
    {
        if (DB::transactionLevel() !== 0) {
            DB::afterCommit(fn () => $this->handle());

            return;
        }
        $files = new CatalogExportFiles($this->export);
        $assessment = $files->assessment();
        $digest = hash('sha256', json_encode([$assessment, array_map(fn ($format): string => $format->value, $this->formats)], JSON_THROW_ON_ERROR));
        if (! $files->claimNotification($digest)) {
            return;
        }
        $this->export->touch('completed_at');
        $actions = [];
        foreach ($this->formats as $format) {
            if ($assessment[$format->value]['download']) {
                $actions[] = $format->getDownloadNotificationAction($this->export, $this->authGuard);
            }
        }
        $formats = array_map(fn ($format): string => $format->value, $this->formats);
        $presentation = app(CatalogDataExchangePolicy::class)->notification($assessment, $formats);
        $notification = Notification::make('catalog-export-'.$this->export->id)
            ->title(__('data_exchange.notifications.export_'.($presentation['phase'] === 'unconfirmed' ? 'unconfirmed_title' : $presentation['phase'])))
            ->body($this->exporter::completionBody($assessment, $formats))
            ->status($presentation['status'])
            ->actions($actions);
        if ($this->connection === 'sync' || ($this->connection === null && config('queue.default') === 'sync')) {
            $notification->persistent()->send();
        } else {
            $notification->sendToDatabase($this->export->user, isEventDispatched: true);
        }
    }
}
