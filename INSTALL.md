# Installing the biteslote Connector in your Laravel website

This guide walks you through connecting your Laravel store to the biteslote POS so
web orders flow straight into the POS and order-status updates flow back.

You will:

1. Install the package via Composer
2. Add your API credentials
3. Run the migrations
4. Map your products to the POS menu
5. Forward orders from your checkout
6. (Optional) Receive live status updates via webhook

**Estimated time:** ~30 minutes.

---

## 0. Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.0+ |
| Laravel | 9, 10, 11 or 12 |
| Composer | 2.x |
| PHP extensions | `curl`, `json` |
| From biteslote | API base URL + API key (`rk_live_…`), issued on your **API & Integrations** page |

> Ask your biteslote contact for: **API URL**, **API key**, and (if you want status
> updates) a **webhook secret**.

---

## 1. Install via Composer

The connector is a private package, so add its repositories to your project's
`composer.json` (top level), then require it.

```jsonc
// composer.json
"repositories": [
    { "type": "vcs", "url": "https://github.com/biteslote/restapi-laravel.git" },
    { "type": "vcs", "url": "https://github.com/biteslote/restapi-sdk.git" }
],
```

Authenticate once on the machine/server (private repos need a token):

```bash
# GitHub personal access token with "repo" read scope
composer config --global --auth github-oauth.github.com <YOUR_TOKEN>
```

Then install:

```bash
composer require biteslote/restapi-laravel:^1.0
```

Laravel auto-discovers the package — no manual provider registration needed.

---

## 2. Publish config & add credentials

```bash
php artisan vendor:publish --tag=biteslote-restapi-config     # API client (base URL + key)
php artisan vendor:publish --tag=biteslote-connector-config   # connector settings
```

Add to your `.env`:

```env
# --- POS API (required) ---
BITESLOTE_API_URL=https://shop.biteslote.com/api/application-integration/v1
BITESLOTE_API_KEY=rk_live_xxxxxxxxxxxxxxxx

# --- Connector behaviour (optional) ---
BITESLOTE_BRANCH_ID=                 # leave blank unless told otherwise; the key usually scopes the branch
BITESLOTE_ORDER_TYPE=delivery        # must match an order type configured on the POS

# --- Webhook (only if you enable status updates in step 6) ---
BITESLOTE_WEBHOOK_SECRET=whsec_xxxxxxxx
```

Verify the connection:

```bash
php artisan tinker
>>> \Biteslote\RestApi\Laravel\RestApi::ping();
```

You should get a success payload. A `401`/`403` means the API key is wrong or not
yet active.

---

## 3. Run the migrations

This creates two tables: `biteslote_product_map` (the link between your products
and POS items) and `biteslote_pos_items` (a local copy of the POS menu).

```bash
php artisan vendor:publish --tag=biteslote-connector-migrations   # optional, only if you want to edit them
php artisan migrate
```

---

## 4. Map your products to the POS menu

**This is the important step.** Your product IDs/names are different from the POS,
so each product must be linked to a POS menu item once.

### 4a. Pull the POS menu and auto-link by SKU

```bash
php artisan biteslote:sync-catalog
```

This downloads the POS menu into `biteslote_pos_items` and automatically links any
of your products whose **SKU** matches a POS item. The command prints how many are
still unmapped.

For SKU auto-mapping to work, seed your products into the map table first (id + sku):

```php
use Biteslote\Connector\Models\ProductMap;

foreach (Product::all() as $p) {
    ProductMap::firstOrCreate(
        ['local_product_id' => $p->id],
        ['local_sku' => $p->sku]
    );
}
// then run: php artisan biteslote:sync-catalog
```

### 4b. Map the rest manually

For products without a matching SKU, link them explicitly (build a small admin
screen, or do it in tinker):

```php
use Biteslote\Connector\Models\ProductMap;

// your product 482  ->  POS menu item 1071
ProductMap::link(482, 1071, branchId: null, extra: [
    'local_sku' => 'BURGER-CL',
    'pos_name'  => 'Classic Burger',
]);
```

To list which POS items are available to map:

```php
\Biteslote\Connector\Models\PosItem::get(['pos_item_id', 'name', 'sku', 'price']);
```

> A product with no mapping will **block** its order (see step 5) — by design, so a
> wrong item never reaches the kitchen.

---

## 5. Forward an order from checkout

Call the forwarder after your order is saved locally. Pass **your own** product IDs —
they are translated automatically.

```php
use Biteslote\Connector\Services\OrderForwarder;
use Biteslote\Connector\Exceptions\UnmappedProductsException;
use Biteslote\RestApi\Exceptions\ApiException;

try {
    $posOrder = app(OrderForwarder::class)->forward([
        'reference'  => $order->id,          // your order id (used so retries don't duplicate)
        'order_type' => 'delivery',
        'note'       => $order->notes,
        'items'      => $order->items->map(fn ($i) => [
            'product_id' => $i->product_id,  // YOUR id
            'quantity'   => $i->qty,
            'note'       => $i->note,
        ])->all(),
        'customer'   => [
            'name'  => $order->customer_name,
            'phone' => $order->customer_phone,
            'email' => $order->customer_email,
        ],
    ]);

    // Save the POS order id so you can match webhook updates later.
    $order->update(['pos_order_id' => $posOrder['id']]);

} catch (UnmappedProductsException $e) {
    // $e->localProductIds = the products you still need to map.
    report($e);
    // ...show the customer/admin a clear error...
} catch (ApiException $e) {
    // POS rejected/unreachable: $e->getMessage(), $e->getCode()
    report($e);
}
```

Tip: run this inside a queued job so a slow POS never blocks checkout.

---

## 6. (Optional) Receive live status updates

If you want POS status changes (confirmed, preparing, ready, cancelled…) reflected
on your site:

1. **Set the secret** in `.env` (`BITESLOTE_WEBHOOK_SECRET`) — get this value from
   biteslote.
2. **Register the endpoint on the POS** (API & Integrations → Webhooks):
   - URL: `https://your-site.com/biteslote/webhook`  *(must be HTTPS & public)*
   - Events: `order.created`, `order.status_changed`
   - Secret: the same value as above
3. **Listen for the event** (e.g. in `AppServiceProvider::boot()` or an EventServiceProvider):

```php
use Biteslote\Connector\Events\PosWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::listen(function (PosWebhookReceived $e) {
    if ($e->type === 'order.status_changed') {
        \App\Models\Order::where('pos_order_id', $e->orderId())
            ->update(['status' => $e->status()]);
    }
});
```

Signature verification is automatic — invalid requests are rejected before your
listener runs.

> If your app uses CSRF/`VerifyCsrfToken`, the webhook route is registered under the
> `api` middleware group and is already exempt. If you changed the route group, add
> the path to your CSRF `$except` list.

---

## 7. Deploying to production

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan biteslote:sync-catalog        # refresh the POS menu after deploy
```

Make sure the production server has the Composer auth token (step 1) and the `.env`
values (step 2).

---

## Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `Could not find package biteslote/restapi-laravel` | Repositories not added to `composer.json`, or no `v1.x` tag, or missing auth token |
| `ping()` returns 401 / 403 | Wrong/inactive API key, or wrong `BITESLOTE_API_URL` |
| `UnmappedProductsException` on checkout | Those products aren't mapped yet — run `biteslote:sync-catalog` and/or map them (step 4) |
| `422 Unknown menu item(s)` from POS | A mapping points to a POS item id that doesn't exist on that branch — re-sync and re-map |
| Webhook not updating orders | Endpoint not registered on POS, secret mismatch, or URL not publicly reachable over HTTPS |
| Orders slow / timing out | Forward inside a queued job; check `BITESLOTE_API_TIMEOUT` |

---

## Quick reference

| Setting | Where |
|---------|-------|
| API URL / key / timeout | `config/biteslote-restapi.php` (env: `BITESLOTE_API_URL`, `BITESLOTE_API_KEY`, `BITESLOTE_API_TIMEOUT`) |
| Branch / order type / webhook | `config/biteslote-connector.php` |
| Product mapping table | `biteslote_product_map` (model `Biteslote\Connector\Models\ProductMap`) |
| POS menu cache | `biteslote_pos_items` (model `Biteslote\Connector\Models\PosItem`) |
| Sync command | `php artisan biteslote:sync-catalog` |
| Forward an order | `app(\Biteslote\Connector\Services\OrderForwarder::class)->forward([...])` |
| Webhook event | `Biteslote\Connector\Events\PosWebhookReceived` |
| Webhook URL | `POST /biteslote/webhook` |
