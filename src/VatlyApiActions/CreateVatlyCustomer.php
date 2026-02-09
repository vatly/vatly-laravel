<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class CreateVatlyCustomer extends BaseVatlyApiAction
{
    public function execute(array $payload, array $filters = []): CreateVatlyCustomerResponse
    {
        $apiResponse = $this->vatlyApiClient->customers->create(
            payload: $payload,
            filters: $filters,
        );

        return CreateVatlyCustomerResponse::fromApiResponse($apiResponse);
    }
}
