<?php

namespace Biteslot\Connector\Services;

use Biteslot\Connector\Events\OrderForwarded;
use Biteslot\RestApi\Client;
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
    /** @var Client */
    private $client;

    /** @var ProductMapper */
    private $mapper;

    /** @var Config */
    private $config;

    /** @var Dispatcher */
    private $events;

    public function __construct(Client $client, ProductMapper $mapper, Config $config, Dispatcher $events)
    {
        $this->client = $client;
        $this->mapper = $mapper;
        $this->config = $config;
        $this->events = $events;
    }

    /**
     * @param array $cart {
     *     items: array<array{product_id:int|string, quantity:int|float, note?:string}>,
     *     customer?: array{name?:string, phone?:string, phone_code?:string, email?:string},
     *     order_type?: string,
     *     note?: string,
     *     reference?: int|string,  // your local order id; used as idempotency key + echoed in the event
     *     discounts?: array<array{label?:string, type?:string, value:float, funded_by?:string}>,
     *     fees?: array<array{label?:string, amount:float, kind?:string, collected_by?:string}>,
     *     customer_paid_total?: float,  // what the customer paid on the site; reconciliation only
     *     tax_amount?: float,           // GST override; POS uses this instead of recomputing (0 = none)
     *     tax_inclusive?: bool          // the supplied tax_amount is already inside item prices
     * }
     *
     * Tax and restaurant charges are recomputed POS-side; discounts/fees you pass are
     * applied (restaurant-funded) or stored as informational (platform-funded). Requires
     * the POS RestApi module v2.4.0+.
     *
     * @return array the POS order payload (full price breakdown)
     *
     * @throws \Biteslot\Connector\Exceptions\UnmappedProductsException
     * @throws \Biteslot\RestApi\Exceptions\ApiException
     */
    public function forward(array $cart, ?string $idempotencyKey = null): array
    {
        $reference = $cart['reference'] ?? null;

        $payload = array_filter([
            'order_type' => $cart['order_type'] ?? $this->config->get('biteslot-connector.default_order_type'),
            'branch_id' => $this->config->get('biteslot-connector.default_branch_id'),
            'note' => $cart['note'] ?? null,
            // The storefront's own order number + platform label, shown on the POS
            // order and kitchen ticket so staff can trace it back to the website.
            'external_reference' => $cart['external_reference'] ?? ($reference !== null ? (string) $reference : null),
            'source' => $cart['source'] ?? $this->config->get('biteslot-connector.order_source'),
            'items' => $this->mapper->resolve($cart['items'] ?? []),
            'customer' => $cart['customer'] ?? null,
            'discounts' => $cart['discounts'] ?? null,
            'fees' => $cart['fees'] ?? null,
            'customer_paid_total' => $cart['customer_paid_total'] ?? null,
            // GST override (null/absent = let the POS compute GST). Use array_key_exists
            // so an explicit 0 (no GST) is still forwarded.
            'tax_amount' => array_key_exists('tax_amount', $cart) ? $cart['tax_amount'] : null,
            'tax_inclusive' => $cart['tax_inclusive'] ?? null,
        ], static fn ($v) => $v !== null && $v !== []);

        $key = $idempotencyKey
            ?? ($reference !== null ? 'local-order-' . $reference : null);

        $posOrder = $this->client->orders()->create($payload, $key);

        $this->events->dispatch(new OrderForwarded($posOrder, $reference));

        return $posOrder;
    }
}
