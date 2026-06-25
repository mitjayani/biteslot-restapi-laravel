<?php

use Biteslot\Connector\Http\Controllers\WebhookController;
use Biteslot\Connector\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

$config = config('biteslot-connector.webhook', []);

Route::post($config['route'] ?? 'biteslot/webhook', WebhookController::class)
    ->middleware(array_merge(
        (array) ($config['middleware'] ?? ['api']),
        [VerifyWebhookSignature::class]
    ))
    ->name('biteslot.webhook');
