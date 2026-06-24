<?php

namespace Biteslote\Connector\Services;

use Biteslote\Connector\Models\PosItem;
use Biteslote\Connector\Models\ProductMap;
use Biteslote\RestApi\Client;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Pulls the POS catalog into a local cache and helps link products to it.
 *
 * `pull()` refreshes biteslote_pos_items (paginated). `autoMapBySku()` then links
 * any storefront product whose SKU matches a POS item — the cheap win before a
 * human maps the remainder in the UI.
 */
class CatalogSync
{
    /** @var Client */
    private $client;

    /** @var Config */
    private $config;

    public function __construct(Client $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Refresh the local POS item cache. Returns the number of items synced.
     */
    public function pull(?int $branchId = null): int
    {
        $branchId = $branchId ?? $this->normalizeBranch($this->config->get('biteslote-connector.default_branch_id'));
        $perPage = (int) $this->config->get('biteslote-connector.sync.per_page', 100);
        $now = now();
        $count = 0;
        $page = 1;

        do {
            $params = array_filter([
                'branch_id' => $branchId,
                'per_page' => $perPage,
                'page' => $page,
            ], static fn ($v) => $v !== null);

            $envelope = $this->client->catalog()->items($params);
            $rows = $envelope['data'] ?? [];

            foreach ($rows as $item) {
                $this->upsertItem($item, $branchId, $now);
                $count++;
            }

            $page++;
            $hasMore = $this->hasMorePages($envelope, count($rows), $perPage);
        } while ($hasMore);

        return $count;
    }

    /**
     * Link unmapped storefront products to POS items by matching SKU.
     * Returns the number of product_map rows newly linked.
     */
    public function autoMapBySku(): int
    {
        $bySku = PosItem::whereNotNull('sku')->get()->keyBy(fn ($i) => strtolower(trim($i->sku)));

        if ($bySku->isEmpty()) {
            return 0;
        }

        $linked = 0;

        ProductMap::unmapped()
            ->whereNotNull('local_sku')
            ->chunkById(200, function ($maps) use ($bySku, &$linked) {
                foreach ($maps as $map) {
                    $item = $bySku->get(strtolower(trim((string) $map->local_sku)));
                    if (! $item) {
                        continue;
                    }

                    $map->update([
                        'pos_item_id' => $item->pos_item_id,
                        'pos_branch_id' => $item->branch_id,
                        'pos_name' => $item->name,
                        'pos_price' => $item->price,
                    ]);
                    $linked++;
                }
            });

        return $linked;
    }

    private function upsertItem(array $item, ?int $branchId, $now): void
    {
        $posItemId = (int) ($item['id'] ?? $item['menu_item_id'] ?? 0);
        if ($posItemId <= 0) {
            return;
        }

        PosItem::updateOrCreate(
            ['pos_item_id' => $posItemId, 'branch_id' => $item['branch_id'] ?? $branchId],
            [
                'sku' => $item['sku'] ?? null,
                'name' => $item['name'] ?? ('Item #' . $posItemId),
                'price' => $item['price'] ?? null,
                'available' => (bool) ($item['in_stock'] ?? $item['available'] ?? true),
                'category' => $item['category'] ?? $item['category_name'] ?? null,
                'raw' => $item,
                'synced_at' => $now,
            ]
        );
    }

    private function hasMorePages(array $envelope, int $returned, int $perPage): bool
    {
        $meta = $envelope['meta'] ?? [];
        if (isset($meta['current_page'], $meta['last_page'])) {
            return (int) $meta['current_page'] < (int) $meta['last_page'];
        }

        // Fall back to "a full page implies there may be more".
        return $returned >= $perPage && $returned > 0;
    }

    private function normalizeBranch($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
