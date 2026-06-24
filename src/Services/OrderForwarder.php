<?php

namespace Biteslote\Connector\Services;

use Biteslote\Connector\Events\OrderForwarded;
use Biteslote\RestApi\Client;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Turns a storefront cart into a POS order and forwards it over the connector API.
 *
 * Product IDs are translated by {@see ProductMapper}; totals/tax are computed POS
 * side. Pass a stable idempotency key (e.g. your local order id) so retries never
 * double-create an order.
 */
class OrderForwarder
{
    public function __construct(
        private Client $client,
        private ProductMapper $mapper,
        private Config $config,
        private Dispatcher $events,
    ) {
    }

    /**
     * @param array $cart {
     *     items: array<array{product_id:int|string, quantity:int|float, note?:string}>,
     *     customer?: array{name?:string, phone?:string, phone_code?:string, email?:string},
     *     order_type?: string,
     *     note?: string,
     *     reference?: int|string   // your local order id; used as idempotency key + echoed in the event
     * }
     *
     * @return array the POS order payload
     *
     * @throws \Biteslote\Connector\Exceptions\UnmappedProductsException
     * @throws \Biteslote\RestApi\Exceptions\ApiException
     */
    public function forward(array $cart, ?string $idempotencyKey = null): array
    {
        $reference = $cart['reference'] ?? null;

        $payload = array_filter([
            'order_type' => $cart['order_type'] ?? $this->config->get('biteslote-connector.default_order_type'),
            'branch_id' => $this->config->get('biteslote-connector.default_branch_id'),
            'note' => $cart['note'] ?? null,
            'items' => $this->mapper->resolve($cart['items'] ?? []),
            'customer' => $cart['customer'] ?? null,
        ], static fn ($v) => $v !== null && $v !== []);

        $key = $idempotencyKey
            ?? ($reference !== null ? 'local-order-' . $reference : null);

        $posOrder = $this->client->orders()->create($payload, $key);

        $this->events->dispatch(new OrderForwarded($posOrder, $reference));

        return $posOrder;
    }
}
