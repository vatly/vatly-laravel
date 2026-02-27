<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

use Vatly\API\Resources\Subscription;

class SwapVatlySubscriptionPlanResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $subscriptionPlanId,
        public readonly int $quantity,
    ) {
        //
    }

    /**
     * @return static
     */
    public static function fromApiResponse(Subscription $response): self
    {
        return new static(
            subscriptionId: $response->id,
            subscriptionPlanId: $response->subscriptionPlanId,
            quantity: $response->quantity,
        );
    }
}
