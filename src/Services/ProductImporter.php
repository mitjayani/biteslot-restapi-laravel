<?php

namespace Biteslot\Connector\Services;

use Biteslot\Connector\Models\ProductMap;
use Biteslot\Connector\Models\SourceSetting;

/**
 * Mirrors the merchant's product table into biteslot_product_map so the wizard
 * has one row per storefront product to map. Only the local_* (display/match)
 * columns are written — an existing pos_item_id link is never touched, so a
 * re-import after the catalog changes keeps every mapping the merchant made.
 */
class ProductImporter
{
    /** @var SourceCatalog */
    private $catalog;

    public function __construct(SourceCatalog $catalog)
    {
        $this->catalog = $catalog;
    }

    /**
     * Pull every product from the configured source into the map table.
     *
     * @return array{imported:int, total:int} rows touched / rows seen
     */
    public function import(SourceSetting $source): array
    {
        $map = $source->columnMap();
        $imported = 0;
        $total = 0;

        $this->catalog->query($source)->chunk(500, function ($rows) use ($map, &$imported, &$total) {
            foreach ($rows as $row) {
                $total++;

                $localId = $map['id'] ?? null;
                if ($localId === null || ! isset($row->{$localId}) || $row->{$localId} === null) {
                    continue;
                }

                $value = static fn (?string $col) => ($col !== null && isset($row->{$col})) ? $row->{$col} : null;

                ProductMap::updateOrCreate(
                    ['local_product_id' => (string) $row->{$localId}],
                    array_filter([
                        'local_sku' => $value($map['sku'] ?? null),
                        'local_name' => $value($map['name'] ?? null),
                        'local_price' => $value($map['price'] ?? null),
                        'local_category' => $value($map['category'] ?? null),
                    ], static fn ($v) => $v !== null)
                );

                $imported++;
            }
        });

        $source->forceFill(['imported_at' => now()])->save();

        return ['imported' => $imported, 'total' => $total];
    }
}
