<?php

namespace App\Filament\Exports\Jobs;

use App\Filament\Exports\ConfiguratorRuleExporter;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Illuminate\Support\Facades\Gate;

class PrepareConfiguratorRulesExport extends PrepareCsvExport
{
    public function handle(): void
    {
        Gate::forUser($this->export->user()->firstOrFail())->authorize('manage-catalog');
        $this->columnMap = ConfiguratorRuleExporter::columnMap($this->options);
        parent::handle();
    }

    public function getExportCsvJob(): string
    {
        return ExportConfiguratorRulesCsv::class;
    }
}
