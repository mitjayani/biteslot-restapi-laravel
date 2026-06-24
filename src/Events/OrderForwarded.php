<?php

namespace Biteslote\Connector\Events;

/**
 * Fired after a storefront cart has been accepted by the POS.
 *
 * Listen to persist the returned POS order id against your local order so you
 * can correlate later status webhooks. The reference you passed to the forwarder
 * (typically your local order id) is echoed back as $reference.
 */
class OrderForwarded
{
    /**
     * @param array            $posOrder  the POS order payload (id, order_number, status, total, ...)
     * @param int|string|null  $reference your local order reference, if supplied
     */
    public function __construct(
        public array $posOrder,
        public int|string|null $reference = null,
    ) {
    }

    public function posOrderId(): ?int
    {
        return isset($this->posOrder['id']) ? (int) $this->posOrder['id'] : null;
    }
}
