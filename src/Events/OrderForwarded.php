<?php

namespace Biteslot\Connector\Events;

/**
 * Fired after a storefront cart has been accepted by the POS.
 *
 * Listen to persist the returned POS order id against your local order so you
 * can correlate later status webhooks. The reference you passed to the forwarder
 * (typically your local order id) is echoed back as $reference.
 */
class OrderForwarded
{
    /** @var array the POS order payload (id, order_number, status, total, ...) */
    public $posOrder;

    /** @var int|string|null your local order reference, if supplied */
    public $reference;

    /**
     * @param int|string|null $reference
     */
    public function __construct(array $posOrder, $reference = null)
    {
        $this->posOrder = $posOrder;
        $this->reference = $reference;
    }

    public function posOrderId(): ?int
    {
        return isset($this->posOrder['id']) ? (int) $this->posOrder['id'] : null;
    }
}
