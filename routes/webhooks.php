<?php

use Biteslote\Connector\Http\Controllers\WebhookController;
use Biteslote\Connector\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

$config = config('biteslote-connector.webhook', []);

Route::post($config['route'] ?? 'biteslote/webhook', WebhookController::class)
    ->middleware(array_merge(
        (array) ($config['middleware'] ?? ['api']),
        [VerifyWebhookSignature::class]
    ))
    ->name('biteslote.webhook');
