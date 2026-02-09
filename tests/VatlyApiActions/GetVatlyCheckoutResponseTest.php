<?php

declare(strict_types=1);

namespace VatlyApiActions;

use Illuminate\Http\RedirectResponse;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\VatlyApiActions\GetVatlyCheckoutResponse;

class GetVatlyCheckoutResponseTest extends BaseTestCase
{
    /** @test */
    public function it_redirects(): void
    {
        $getVatlyCheckoutResponse = new GetVatlyCheckoutResponse(
            checkoutId: 'checkout_dummy_1',
            checkoutUrl: 'https://dummy.com',
        );

        $redirect = $getVatlyCheckoutResponse->redirect();

        $this->assertInstanceOf(RedirectResponse::class, $redirect);
        $this->assertEquals('https://dummy.com', $redirect->getTargetUrl());
    }
}
