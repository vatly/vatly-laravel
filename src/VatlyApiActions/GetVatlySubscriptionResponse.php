<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

use Vatly\API\Resources\Subscription;

class GetVatlySubscriptionResponse
{
    public function __construct(
        public readonly string $subscriptionId,
    ) {
        //
    }

    /**
     * @return static
     */
    public static function fromApiResponse(Subscription $response): static
    {
        return new static(
            subscriptionId: $response->id,
        );
    }

    /**
     * @return static
     */
    public static function fromVatlyResponse(Subscription $response): self
    {
        return new static(
            subscriptionId: $response->id,
        );
    }
}
