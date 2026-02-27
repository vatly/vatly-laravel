<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class GetVatlySubscription extends BaseVatlyApiAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $subscriptionId, array $parameters = []): GetVatlySubscriptionResponse
    {
        $response = $this->vatlyApiClient->subscriptions->get($subscriptionId, $parameters);

        return GetVatlySubscriptionResponse::fromVatlyResponse($response);
    }
}
