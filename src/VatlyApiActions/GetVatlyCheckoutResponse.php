<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

use Illuminate\Http\RedirectResponse;
use Vatly\API\Resources\Checkout;

class GetVatlyCheckoutResponse
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly ?string $checkoutUrl,
    ) {
        //
    }

    public static function fromApiResponse(Checkout $response): self
    {
        return new static(
            checkoutId: $response->id,
            checkoutUrl: $response->links->checkoutUrl->href ?? null,
        );
    }

    public function redirect(): RedirectResponse
    {
        return new RedirectResponse($this->checkoutUrl);
    }
}
