<?php

namespace Biteslote\Connector\Console;

use Biteslote\Connector\Models\ProductMap;
use Biteslote\Connector\Services\CatalogSync;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    protected $signature = 'biteslote:sync-catalog
        {--branch= : POS branch id to sync (defaults to config/key branch)}
        {--no-automap : Skip SKU auto-mapping after the pull}';

    protected $description = 'Pull the POS catalog into the local cache and auto-map storefront products by SKU';

    public function handle(CatalogSync $sync): int
    {
        $branch = $this->option('branch') !== null ? (int) $this->option('branch') : null;

        $this->info('Pulling POS catalog…');
        $synced = $sync->pull($branch);
        $this->info("Synced {$synced} POS item(s).");

        if (! $this->option('no-automap')) {
            $linked = $sync->autoMapBySku();
            $this->info("Auto-mapped {$linked} product(s) by SKU.");
        }

        $pending = ProductMap::unmapped()->count();
        if ($pending > 0) {
            $this->warn("{$pending} storefront product(s) still need a POS mapping.");
        }

        return self::SUCCESS;
    }
}
