<?php

declare(strict_types=1);

namespace VatlyApiActions;

use Vatly\API\Endpoints\CheckoutEndpoint;
use Vatly\API\Resources\Checkout;
use Vatly\API\Resources\Links\CheckoutLinks;
use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\GetVatlyCheckout;
use Vatly\Laravel\VatlyApiActions\GetVatlyCheckoutResponse;

class GetVatlyCheckoutTest extends BaseTestCase
{
    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(GetVatlyCheckout::class, new GetVatlyCheckout(vatlyApiClient: new VatlyApiClient));
    }

    /** @test */
    public function it_executes()
    {
        $vatlyCheckoutApiResponse = new Checkout(new VatlyApiClient());
        $vatlyCheckoutApiResponse->id = 'checkout_dummy_1';
        $vatlyCheckoutApiResponse->links = new CheckoutLinks;
        $vatlyCheckoutApiResponse->links->checkoutUrl = new Link('https://foo-bar.com', 'json');

        $mockCheckoutsEndpoint = $this->createMock(CheckoutEndpoint::class);
        $mockCheckoutsEndpoint
            ->expects($this->once())
            ->method('get')
            ->with('checkout_dummy_1')
            ->willReturn($vatlyCheckoutApiResponse);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'checkouts',
            $mockCheckoutsEndpoint,
        );

        $createVatlyCheckout = new GetVatlyCheckout($client);

        $response = $createVatlyCheckout->execute('checkout_dummy_1');

        $this->assertInstanceOf(GetVatlyCheckoutResponse::class, $response);
        $this->assertEquals('checkout_dummy_1', $response->checkoutId);
        $this->assertEquals('https://foo-bar.com', $response->checkoutUrl);
    }
}
