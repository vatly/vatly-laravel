<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\VatlyApiActions;

use Vatly\API\Endpoints\CheckoutEndpoint;
use Vatly\API\Resources\Checkout;
use Vatly\API\Resources\Links\CheckoutLinks;
use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckout;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckoutResponse;

class CreateVatlyCheckoutTest extends BaseTestCase
{
    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(CreateVatlyCheckout::class, new CreateVatlyCheckout(vatlyApiClient: new VatlyApiClient));
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
            ->method('create')
            ->with(['a_dummy_payload_here'], ['a_dummy_filter_here'])
            ->willReturn($vatlyCheckoutApiResponse);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'checkouts',
            $mockCheckoutsEndpoint,
        );

        $createVatlyCheckout = new CreateVatlyCheckout($client);

        $response = $createVatlyCheckout->execute([
            'a_dummy_payload_here',
        ], ['a_dummy_filter_here']);

        $this->assertInstanceOf(CreateVatlyCheckoutResponse::class, $response);
        $this->assertEquals('checkout_dummy_1', $response->checkoutId);
        $this->assertEquals('https://foo-bar.com', $response->checkoutUrl);
    }
}
