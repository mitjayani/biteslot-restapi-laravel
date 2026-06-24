<?php

namespace Biteslote\Connector\Events;

/**
 * Fired for every verified inbound POS webhook (order.created, order.status_changed, …).
 *
 * Listen to sync POS status back onto your local order. The signature has already
 * been verified before this event is dispatched.
 */
class PosWebhookReceived
{
    /**
     * @param string $type    the event name, e.g. "order.status_changed"
     * @param array  $payload the event data block
     */
    public function __construct(
        public string $type,
        public array $payload,
    ) {
    }

    public function orderId(): ?int
    {
        return isset($this->payload['order_id'])
            ? (int) $this->payload['order_id']
            : (isset($this->payload['id']) ? (int) $this->payload['id'] : null);
    }

    public function status(): ?string
    {
        return $this->payload['status'] ?? null;
    }
}
