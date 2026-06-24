<?php

namespace Biteslote\Connector\Http\Middleware;

use Biteslote\RestApi\Webhook\SignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects inbound POS webhooks whose HMAC signature does not match the
 * configured endpoint secret. Uses the raw request body, so it must run before
 * anything that would re-serialize the payload.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('biteslote-connector.webhook.secret', '');
        $signature = (string) $request->header('X-Biteslote-Signature', '');

        if (! SignatureVerifier::verify($request->getContent(), $signature, $secret)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        return $next($request);
    }
}
