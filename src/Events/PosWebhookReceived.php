<?php

namespace Biteslot\Connector\Events;

/**
 * Fired for every verified inbound POS webhook (order.created, order.status_changed, …).
 *
 * Listen to sync POS status back onto your local order. The signature has already
 * been verified before this event is dispatched.
 */
class PosWebhookReceived
{
    /** @var string the event name, e.g. "order.status_changed" */
    public $type;

    /** @var array the event data block */
    public $payload;

    public function __construct(string $type, array $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    public function orderId(): ?int
    {
        if (isset($this->payload['order_id'])) {
            return (int) $this->payload['order_id'];
        }

        return isset($this->payload['id']) ? (int) $this->payload['id'] : null;
    }

    public function status(): ?string
    {
        return $this->payload['status'] ?? null;
    }
}
