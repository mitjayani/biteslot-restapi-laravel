<?php

namespace Biteslot\Connector\Services;

use Biteslot\Connector\Exceptions\UnmappedProductsException;
use Biteslot\Connector\Models\ProductMap;

/**
 * Translates storefront cart lines into POS order lines.
 *
 * The POS /v1/orders endpoint only accepts its own menu_item ids and rejects
 * anything else, so every line must be resolved through biteslot_product_map
 * first. Unmapped products are collected and reported together, never guessed.
 */
class ProductMapper
{
    /**
     * @param array<int,array{product_id:int|string, quantity:int|float, note?:string|null}> $items
     *
     * @return array<int,array{id:int, quantity:float, note?:string}> POS-ready line items
     *
     * @throws UnmappedProductsException
     */
    public function resolve(array $items): array
    {
        $localIds = array_map(
            static fn ($line) => (string) $line['product_id'],
            $items
        );

        $maps = ProductMap::mapped()
            ->whereIn('local_product_id', array_unique($localIds))
            ->get()
            ->keyBy('local_product_id');

        $lines = [];
        $unmapped = [];

        foreach ($items as $line) {
            $localId = (string) $line['product_id'];
            $map = $maps->get($localId);

            if (! $map) {
                $unmapped[] = $localId;

                continue;
            }

            $posLine = [
                'id' => (int) $map->pos_item_id,
                'quantity' => (float) $line['quantity'],
            ];

            if (! empty($line['note'])) {
                $posLine['note'] = (string) $line['note'];
            }

            $lines[] = $posLine;
        }

        if ($unmapped) {
            throw new UnmappedProductsException(array_unique($unmapped));
        }

        return $lines;
    }

    /**
     * Report which of the given storefront product IDs are not yet mapped.
     *
     * @param array<int,int|string> $localProductIds
     *
     * @return array<int,string>
     */
    public function unmappedAmong(array $localProductIds): array
    {
        $ids = array_map('strval', $localProductIds);

        $mapped = ProductMap::mapped()
            ->whereIn('local_product_id', $ids)
            ->pluck('local_product_id')
            ->all();

        return array_values(array_diff(array_unique($ids), $mapped));
    }
}
