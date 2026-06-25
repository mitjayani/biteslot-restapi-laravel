<?php

namespace Biteslot\Connector\Exceptions;

use RuntimeException;

/**
 * Thrown when a cart contains storefront products that have no POS mapping.
 *
 * Carries the offending local product IDs so the caller can surface a precise
 * error instead of letting the wrong items reach the kitchen.
 */
class UnmappedProductsException extends RuntimeException
{
    /** @var array<int,string> */
    public array $localProductIds;

    /** @param array<int,string> $localProductIds */
    public function __construct(array $localProductIds)
    {
        $this->localProductIds = array_values($localProductIds);

        parent::__construct(
            'These storefront products are not mapped to a POS item: '
            . implode(', ', $this->localProductIds)
        );
    }
}
