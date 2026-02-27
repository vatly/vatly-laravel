<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class CreateVatlyCheckout extends BaseVatlyApiAction
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     */
    public function execute(array $payload, array $filters = []): CreateVatlyCheckoutResponse
    {
        $apiResponse = $this->vatlyApiClient->checkouts->create(
            payload: $payload,
            filters: $filters,
        );

        return CreateVatlyCheckoutResponse::fromApiResponse($apiResponse);
    }
}
