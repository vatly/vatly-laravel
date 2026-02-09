<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Vatly\API\Resources\Checkout;

class CreateVatlyCheckoutResponse implements Responsable
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly string $checkoutUrl,
    ) {
        //
    }

    public static function fromApiResponse(Checkout $checkout): self
    {
        return new self(
            checkoutId: $checkout->id,
            checkoutUrl: $checkout->links->checkoutUrl->href,
        );
    }

    public function redirect(): RedirectResponse
    {
        return new RedirectResponse($this->checkoutUrl);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return $this->redirect();
    }
}
