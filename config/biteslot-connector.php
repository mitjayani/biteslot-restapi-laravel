<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default branch
    |--------------------------------------------------------------------------
    | The POS branch_id orders are forwarded to and the catalog is synced from.
    | An API key is usually already scoped to one branch on the POS side, so this
    | can be left null and the POS will resolve the key's branch.
    */
    'default_branch_id' => env('BITESLOT_BRANCH_ID'),

    /*
    |--------------------------------------------------------------------------
    | Default order type
    |--------------------------------------------------------------------------
    | Sent as `order_type` when a forwarded cart does not specify one. Must match
    | an OrderType slug/type configured on the POS (e.g. delivery, pickup).
    */
    'default_order_type' => env('BITESLOT_ORDER_TYPE', 'delivery'),

    /*
    |--------------------------------------------------------------------------
    | Catalog sync
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Product source (read by the setup wizard)
    |--------------------------------------------------------------------------
    | The DB connection whose tables the wizard lists when the merchant picks
    | "the table that contains their products". Null = the app's default
    | connection. The chosen table + column mapping itself is stored in
    | biteslot_source_settings, not here.
    */
    'source' => [
        'connection' => env('BITESLOT_SOURCE_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Setup wizard
    |--------------------------------------------------------------------------
    | The guided product-mapping UI. IMPORTANT: add your own auth/authorization
    | middleware so only an admin can reach it, e.g.
    |   'middleware' => ['web', 'auth', 'can:manage-biteslot'],
    | Set 'enabled' => false to remove the routes entirely (map via CLI instead).
    */
    'wizard' => [
        'enabled' => env('BITESLOT_WIZARD_ENABLED', true),
        'prefix' => env('BITESLOT_WIZARD_PREFIX', 'biteslot/setup'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound webhooks (POS -> this site)
    |--------------------------------------------------------------------------
    | The POS posts order.created / order.status_changed here. `secret` is the
    | endpoint secret you set when registering the webhook on the POS; deliveries
    | are HMAC-signed and verified before the PosWebhookReceived event fires.
    */
    'webhook' => [
        'enabled' => true,
        'route' => env('BITESLOT_WEBHOOK_ROUTE', 'biteslot/webhook'),
        'secret' => env('BITESLOT_WEBHOOK_SECRET', ''),
        'middleware' => ['api'],
    ],
];
