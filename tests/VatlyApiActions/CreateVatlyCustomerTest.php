<?php

declare(strict_types=1);

namespace VatlyApiActions;

use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Resources\Customer;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCustomer;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCustomerResponse;

class CreateVatlyCustomerTest extends BaseTestCase
{
    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(CreateVatlyCustomer::class, new CreateVatlyCustomer(vatlyApiClient: new VatlyApiClient));
    }

    /** @test */
    public function it_executes()
    {
        $vatlyCustomerApiResponse = new Customer(new VatlyApiClient());
        $vatlyCustomerApiResponse->id = 'customer_dummy_1';

        $mockCustomersEndpoint = $this->createMock(CustomerEndpoint::class);
        $mockCustomersEndpoint
            ->expects($this->once())
            ->method('create')
            ->with(['a_dummy_payload_here'], ['a_dummy_filter_here'])
            ->willReturn($vatlyCustomerApiResponse);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'customers',
            $mockCustomersEndpoint,
        );

        $createVatlyCustomer = new CreateVatlyCustomer($client);

        $response = $createVatlyCustomer->execute([
            'a_dummy_payload_here',
        ], ['a_dummy_filter_here']);

        $this->assertInstanceOf(CreateVatlyCustomerResponse::class, $response);
        $this->assertEquals('customer_dummy_1', $response->customerId);
    }
}
