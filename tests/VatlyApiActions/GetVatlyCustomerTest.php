<?php

declare(strict_types=1);

namespace VatlyApiActions;

use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Resources\Customer;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\GetVatlyCustomer;
use Vatly\Laravel\VatlyApiActions\GetVatlyCustomerResponse;

class GetVatlyCustomerTest extends BaseTestCase
{
    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(GetVatlyCustomer::class, new GetVatlyCustomer(vatlyApiClient: new VatlyApiClient));
    }

    /** @test */
    public function it_executes(): void
    {
        $vatlyCheckoutApiResponse = new Customer(new VatlyApiClient);
        $vatlyCheckoutApiResponse->id = 'customer_dummy_1';

        $mockCustomersEndpoint = $this->createMock(CustomerEndpoint::class);
        $mockCustomersEndpoint
            ->expects($this->once())
            ->method('get')
            ->with('customer_dummy_1')
            ->willReturn($vatlyCheckoutApiResponse);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'customers',
            $mockCustomersEndpoint,
        );

        $getVatlyCustomer = new GetVatlyCustomer($client);

        $response = $getVatlyCustomer->execute('customer_dummy_1');

        $this->assertInstanceOf(GetVatlyCustomerResponse::class, $response);
        $this->assertEquals('customer_dummy_1', $response->customerId);
    }
}
