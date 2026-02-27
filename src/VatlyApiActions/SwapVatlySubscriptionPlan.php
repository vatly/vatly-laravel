<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class SwapVatlySubscriptionPlan extends BaseVatlyApiAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(
        string $subscriptionId,
        string $newPlanId,
        array $parameters = [],
    ): SwapVatlySubscriptionPlanResponse {
        $response = $this->vatlyApiClient->subscriptions->update($subscriptionId, array_merge(
            ['subscriptionPlanId' => $newPlanId],
            $parameters,
        ));

        return SwapVatlySubscriptionPlanResponse::fromApiResponse($response);
    }
}
