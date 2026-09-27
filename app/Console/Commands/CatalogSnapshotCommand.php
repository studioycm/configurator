<?php

namespace App\Console\Commands;

use App\Services\CatalogRevisions;
use App\Services\CatalogSnapshots;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogSnapshotCommand extends Command
{
    protected $signature = 'catalog:snapshot {group : Internal leaf Group ID} {--invalidate : Advance the revision and replace the cached dataset after out-of-band changes}';

    protected $description = 'Warm or audit a compact catalog snapshot, with targeted invalidation';

    public function handle(CatalogSnapshots $snapshots, CatalogRevisions $revisions): int
    {
        $groupId = filter_var($this->argument('group'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($groupId === false) {
            $this->error('Provide a positive internal Group ID.');

            return self::INVALID;
        }
        $snapshots->revision($groupId);
        if ($this->option('invalidate')) {
            DB::transaction(fn () => $revisions->advance([$groupId]));
            Cache::forget($snapshots->key($groupId));
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = hrtime(true);
        try {
            $snapshot = $snapshots->get($groupId);
            $json = json_encode($snapshot->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $queries = DB::getQueryLog();
            $this->line(json_encode([
                'group_id' => (string) $groupId, 'revision' => $snapshot->revision(), 'products' => count($snapshot->data['rows']),
                'json_bytes' => strlen($json), 'gzip_bytes' => strlen(gzencode($json)), 'diagnostics' => $snapshot->data['diagnostics'],
                'sql_count' => count($queries), 'sql_ms' => array_sum(array_column($queries, 'time')), 'elapsed_ms' => (hrtime(true) - $started) / 1e6,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } finally {
            DB::disableQueryLog();
        }

        return self::SUCCESS;
    }
}
