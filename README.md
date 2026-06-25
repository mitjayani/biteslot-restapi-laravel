# biteslot Laravel Connector

Laravel integration for the **biteslot POS connector API**. It solves the core
problem of any storefront ↔ POS link: **your website's product IDs and names
never match the POS menu-item IDs.** Orders are translated through a mapping
table before they reach the POS, so the right items always hit the kitchen.

Built on top of [`biteslot/restapi-sdk`](../php).

```
Website order ──> ProductMapper (biteslot_product_map) ──> POS /v1/orders
   (local id 482)         local 482 → pos_item 1071            { items:[{id:1071,...}] }
```

## What you get

- **`biteslot_product_map`** — the authoritative local-product → POS-item link.
- **`biteslot_pos_items`** — a synced snapshot of the POS catalog for building a
  mapping UI and matching by SKU.
- **`ProductMapper`** — translates a cart to POS line items, or throws
  `UnmappedProductsException` listing exactly which products aren't mapped.
- **`OrderForwarder`** — maps + forwards a cart to the POS, idempotently.
- **`CatalogSync`** + `php artisan biteslot:sync-catalog` — pull the catalog and
  auto-map by SKU.
- **Webhook receiver** — verified inbound POS webhooks re-dispatched as the
  `PosWebhookReceived` event so you can sync status back to your order.

## Install

```bash
composer require biteslot/restapi-laravel
php artisan vendor:publish --tag=biteslot-connector-config
php artisan migrate
```

Credentials live in the SDK config (`config/biteslot-restapi.php`):

```env
BITESLOT_API_URL=https://shop.example.com/api/application-integration/v1
BITESLOT_API_KEY=rk_live_xxxxxxxx

# this package
BITESLOT_BRANCH_ID=12              # optional; the key usually already scopes a branch
BITESLOT_ORDER_TYPE=delivery
BITESLOT_WEBHOOK_SECRET=whsec_...  # the endpoint secret you set on the POS
```

## 1. Map your products

Sync the POS catalog and let SKU matches link themselves:

```bash
php artisan biteslot:sync-catalog
```

Seed your storefront products (id + sku) into `biteslot_product_map` and run the
command — anything with a matching SKU is linked automatically. Map the rest in
your own admin screen:

```php
use Biteslot\Connector\Models\ProductMap;

ProductMap::link($localProduct->id, $posItemId, $branchId, [
    'local_sku' => $localProduct->sku,
    'pos_name'  => $posItemName,
]);
```

## 2. Forward an order

```php
use Biteslot\Connector\Services\OrderForwarder;
use Biteslot\Connector\Exceptions\UnmappedProductsException;

try {
    $posOrder = app(OrderForwarder::class)->forward([
        'reference'  => $order->id,            // used as the idempotency key
        'order_type' => 'delivery',
        'note'       => $order->notes,
        'items'      => $order->lines->map(fn ($l) => [
            'product_id' => $l->product_id,    // YOUR id — translated for you
            'quantity'   => $l->qty,
            'note'       => $l->note,
        ])->all(),
        'customer'   => [
            'name'  => $order->customer_name,
            'phone' => $order->customer_phone,
            'email' => $order->customer_email,
        ],
    ]);

    // $posOrder['id'] is the POS order id — store it on your order.
} catch (UnmappedProductsException $e) {
    // $e->localProductIds — block checkout / alert an admin
}
```

Retrying with the same `reference` returns the same POS order (idempotent), so a
double-submit never creates two orders.

## 3. Sync status back

Register the webhook endpoint on the POS (API & Integrations → Webhooks) pointing
to `https://your-site.com/biteslot/webhook` with events `order.created`,
`order.status_changed`, then listen:

```php
use Biteslot\Connector\Events\PosWebhookReceived;

Event::listen(function (PosWebhookReceived $e) {
    if ($e->type === 'order.status_changed') {
        Order::where('pos_order_id', $e->orderId())->update(['status' => $e->status()]);
    }
});
```

## Why a mapping table (not name matching)

- Merchant can rename / re-ID products on either side without breaking orders.
- Unmapped lines fail loudly with the offending IDs — never a silent wrong item.
- Per-product, so Woo + Shopify + this Laravel site can each keep their own map.
