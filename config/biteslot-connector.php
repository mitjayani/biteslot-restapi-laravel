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
