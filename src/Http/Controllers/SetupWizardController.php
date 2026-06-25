<?php

namespace Biteslot\Connector\Http\Controllers;

use Biteslot\Connector\Models\PosItem;
use Biteslot\Connector\Models\ProductMap;
use Biteslot\Connector\Models\SourceSetting;
use Biteslot\Connector\Services\CatalogSync;
use Biteslot\Connector\Services\ProductImporter;
use Biteslot\Connector\Services\SourceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The product-mapping setup wizard shipped with the connector.
 *
 * Three steps, all self-contained in the package so any merchant site gets the
 * same guided flow regardless of platform:
 *   1. Select the table that holds their products (+ map its columns).
 *   2. Sync the BiteSlot POS catalog.
 *   3. Map each storefront product to a POS item.
 *
 * The blade views call the json* endpoints below via fetch(); there is no JS
 * framework dependency on the host app.
 */
class SetupWizardController extends Controller
{
    /** @var SourceCatalog */
    private $catalog;

    /** @var ProductImporter */
    private $importer;

    /** @var CatalogSync */
    private $sync;

    public function __construct(SourceCatalog $catalog, ProductImporter $importer, CatalogSync $sync)
    {
        $this->catalog = $catalog;
        $this->importer = $importer;
        $this->sync = $sync;
    }

    /* ---------------------------------------------------------------------
     | Pages
     * ------------------------------------------------------------------- */

    public function index()
    {
        return redirect()->route('biteslot.setup.step1');
    }

    public function step1()
    {
        $source = SourceSetting::current();

        return view('biteslot-connector::wizard.step1', [
            'step' => 1,
            'saved' => $source->only([
                'source_table', 'col_id', 'col_sku', 'col_name', 'col_price', 'col_category',
            ]),
        ]);
    }

    public function step2()
    {
        $lastSynced = PosItem::max('synced_at');

        return view('biteslot-connector::wizard.step2', [
            'step' => 2,
            'posCount' => PosItem::count(),
            'lastSyncedAt' => $lastSynced ? Carbon::parse($lastSynced) : null,
        ]);
    }

    public function step3()
    {
        return view('biteslot-connector::wizard.step3', [
            'step' => 3,
            'summary' => $this->summaryData(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | JSON endpoints (consumed by the wizard's fetch() calls)
     * ------------------------------------------------------------------- */

    /** Tables on the source connection the merchant can pick from. */
    public function tables(): JsonResponse
    {
        return $this->safe(fn () => ['tables' => $this->catalog->tables()]);
    }

    /** Columns of a chosen table + a best-guess role mapping. */
    public function columns(Request $request): JsonResponse
    {
        $table = (string) $request->query('table', '');

        return $this->safe(fn () => [
            'columns' => $this->catalog->columns($table),
            'guess' => $this->catalog->guessColumns($table),
        ]);
    }

    /** Persist the chosen table + column map, then import the products. */
    public function saveSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_table' => ['required', 'string'],
            'col_id' => ['required', 'string'],
            'col_sku' => ['nullable', 'string'],
            'col_name' => ['nullable', 'string'],
            'col_price' => ['nullable', 'string'],
            'col_category' => ['nullable', 'string'],
        ]);

        return $this->safe(function () use ($data) {
            $source = SourceSetting::current();
            $source->fill([
                'connection' => config('biteslot-connector.source.connection'),
                'source_table' => $data['source_table'],
                'col_id' => $data['col_id'],
                'col_sku' => $data['col_sku'] ?? null,
                'col_name' => $data['col_name'] ?? null,
                'col_price' => $data['col_price'] ?? null,
                'col_category' => $data['col_category'] ?? null,
            ])->save();

            $result = $this->importer->import($source);

            return [
                'saved' => true,
                'imported' => $result['imported'],
                'total' => $result['total'],
            ];
        });
    }

    /** Sync the POS catalog into the local cache (+ auto-map by SKU). */
    public function syncCatalog(Request $request): JsonResponse
    {
        return $this->safe(function () use ($request) {
            $branch = $request->input('branch');
            $synced = $this->sync->pull($branch !== null && $branch !== '' ? (int) $branch : null);
            $linked = $this->sync->autoMapBySku();

            return [
                'synced' => $synced,
                'auto_mapped' => $linked,
                'pos_count' => PosItem::count(),
            ];
        });
    }

    /** Searchable POS item list for the mapping dropdowns. */
    public function posItems(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $items = PosItem::query()
            ->when($search !== '', function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            })
            ->orderBy('name')
            ->limit(1000)
            ->get(['pos_item_id', 'branch_id', 'name', 'sku', 'price'])
            ->map(fn (PosItem $i) => [
                'pos_item_id' => $i->pos_item_id,
                'branch_id' => $i->branch_id,
                'name' => $i->name,
                'sku' => $i->sku,
                'price' => $i->price,
            ]);

        return response()->json(['items' => $items]);
    }

    /** Paginated storefront products + their current mapping. */
    public function products(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));

        $query = ProductMap::query()
            ->when($search !== '', function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('local_name', 'like', $term)
                        ->orWhere('local_sku', 'like', $term)
                        ->orWhere('local_product_id', 'like', $term);
                });
            })
            ->when($status === 'mapped', fn ($q) => $q->whereNotNull('pos_item_id'))
            ->when($status === 'unmapped', fn ($q) => $q->whereNull('pos_item_id'))
            ->orderBy('local_name')
            ->orderBy('local_product_id');

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (ProductMap $m) => [
                'local_product_id' => $m->local_product_id,
                'local_sku' => $m->local_sku,
                'local_name' => $m->local_name,
                'local_price' => $m->local_price,
                'local_category' => $m->local_category,
                'pos_item_id' => $m->pos_item_id,
                'pos_name' => $m->pos_name,
                'pos_price' => $m->pos_price,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Link (or clear) one storefront product's POS mapping. */
    public function map(Request $request): JsonResponse
    {
        $data = $request->validate([
            'local_product_id' => ['required'],
            'pos_item_id' => ['nullable'],
        ]);

        return $this->safe(function () use ($data) {
            $map = ProductMap::where('local_product_id', (string) $data['local_product_id'])->firstOrFail();

            if (empty($data['pos_item_id'])) {
                $map->update([
                    'pos_item_id' => null,
                    'pos_branch_id' => null,
                    'pos_name' => null,
                    'pos_price' => null,
                ]);

                return ['mapped' => false] + $this->summaryData();
            }

            $item = PosItem::where('pos_item_id', (int) $data['pos_item_id'])->firstOrFail();
            $map->update([
                'pos_item_id' => $item->pos_item_id,
                'pos_branch_id' => $item->branch_id,
                'pos_name' => $item->name,
                'pos_price' => $item->price,
                'is_active' => true,
            ]);

            return ['mapped' => true] + $this->summaryData();
        });
    }

    /** Re-run SKU auto-mapping on demand. */
    public function autoMap(): JsonResponse
    {
        return $this->safe(fn () => ['auto_mapped' => $this->sync->autoMapBySku()] + $this->summaryData());
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->summaryData());
    }

    /* ---------------------------------------------------------------------
     | Helpers
     * ------------------------------------------------------------------- */

    /** @return array{total:int, mapped:int, unmapped:int, pos_count:int} */
    private function summaryData(): array
    {
        $total = ProductMap::count();
        $mapped = ProductMap::whereNotNull('pos_item_id')->count();

        return [
            'total' => $total,
            'mapped' => $mapped,
            'unmapped' => $total - $mapped,
            'pos_count' => PosItem::count(),
        ];
    }

    /**
     * Run a closure and return its array as JSON, turning any failure into a
     * clean { error } response so the wizard can show it inline.
     *
     * @param  callable():array  $fn
     */
    private function safe(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
