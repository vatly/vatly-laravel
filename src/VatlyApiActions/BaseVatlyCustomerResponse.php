<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

use Vatly\API\Resources\Customer;

abstract class BaseVatlyCustomerResponse
{
    public function __construct(
        public readonly string $customerId,
    ) {
        //
    }

    /**
     * @return static
     */
    public static function fromApiResponse(Customer $response): static
    {
        return new static(
            customerId: $response->id,
        );
    }
}
