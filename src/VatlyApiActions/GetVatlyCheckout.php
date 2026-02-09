<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class GetVatlyCheckout extends BaseVatlyApiAction
{
    public function execute(string $checkoutId, array $parameters = []): GetVatlyCheckoutResponse
    {
        $response = $this->vatlyApiClient->checkouts->get($checkoutId, $parameters);

        return GetVatlyCheckoutResponse::fromApiResponse($response);
    }
}
