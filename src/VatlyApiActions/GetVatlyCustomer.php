<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class GetVatlyCustomer extends BaseVatlyApiAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $id, array $parameters = []): GetVatlyCustomerResponse
    {
        $response = $this->vatlyApiClient->customers->get($id, $parameters);

        return GetVatlyCustomerResponse::fromApiResponse($response);
    }
}
