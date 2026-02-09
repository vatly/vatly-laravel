<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\VatlyApiActions;

use Illuminate\Http\RedirectResponse;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckoutResponse;

class CreateVatlyCheckoutResponseTest extends BaseTestCase
{
    /** @test */
    public function it_redirects(): void
    {
        $createVatlyCheckoutResponse = new CreateVatlyCheckoutResponse(
            checkoutId: 'checkout_dummy_1',
            checkoutUrl: 'https://dummy.com',
        );

        $redirect = $createVatlyCheckoutResponse->redirect();

        $this->assertInstanceOf(RedirectResponse::class, $redirect);
        $this->assertEquals('https://dummy.com', $redirect->getTargetUrl());
    }
}
