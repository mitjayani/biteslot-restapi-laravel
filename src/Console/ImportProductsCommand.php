<?php

namespace Biteslot\Connector\Console;

use Biteslot\Connector\Models\ProductMap;
use Biteslot\Connector\Models\SourceSetting;
use Biteslot\Connector\Services\ProductImporter;
use Illuminate\Console\Command;

/**
 * Re-import the merchant's products from the source table configured in the
 * setup wizard. Useful in deploy scripts / cron so new products appear in the
 * mapping screen without anyone re-running the wizard.
 */
class ImportProductsCommand extends Command
{
    protected $signature = 'biteslot:import-products';

    protected $description = 'Re-import storefront products from the configured source table into the mapping table';

    public function handle(ProductImporter $importer): int
    {
        $source = SourceSetting::current();

        if (! $source->isConfigured()) {
            $this->error('No product source configured yet. Open the setup wizard (Step 1) first.');

            return 1;
        }

        $this->info("Importing from [{$source->source_table}]…");
        $result = $importer->import($source);
        $this->info("Imported {$result['imported']} of {$result['total']} product(s).");

        $pending = ProductMap::unmapped()->count();
        if ($pending > 0) {
            $this->warn("{$pending} product(s) still need a POS mapping (Step 3 of the wizard).");
        }

        return 0;
    }
}
