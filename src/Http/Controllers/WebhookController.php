<?php

namespace Biteslote\Connector\Http\Controllers;

use Biteslote\Connector\Events\PosWebhookReceived;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives verified POS webhooks and re-broadcasts them as a Laravel event.
 *
 * The route is signature-checked by VerifyWebhookSignature, so by the time we
 * get here the payload is trusted. Keep this thin: respond 200 fast and let
 * application listeners do the work.
 */
class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events): JsonResponse
    {
        $type = (string) $request->input('event', $request->input('type', 'unknown'));
        $payload = (array) $request->input('data', $request->except(['event', 'type']));

        $events->dispatch(new PosWebhookReceived($type, $payload));

        return response()->json(['received' => true]);
    }
}
