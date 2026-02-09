<?php

declare(strict_types=1);

namespace VatlyApiActions;

use Vatly\API\Endpoints\SubscriptionEndpoint;
use Vatly\API\Resources\Subscription;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\GetVatlySubscription;
use Vatly\Laravel\VatlyApiActions\GetVatlySubscriptionResponse;

class GetVatlySubscriptionTest extends BaseTestCase
{
    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(GetVatlySubscription::class, new GetVatlySubscription(vatlyApiClient: new VatlyApiClient));
    }

    /** @test */
    public function it_executes()
    {
        $vatlySubscriptionApiResponse = new Subscription(new VatlyApiClient());
        $vatlySubscriptionApiResponse->id = 'subscription_dummy_1';

        $mockSubscriptionsEndpoint = $this->createMock(SubscriptionEndpoint::class);
        $mockSubscriptionsEndpoint
            ->expects($this->once())
            ->method('get')
            ->with('subscription_dummy_1')
            ->willReturn($vatlySubscriptionApiResponse);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'subscriptions',
            $mockSubscriptionsEndpoint,
        );

        $createVatlySubscription = new GetVatlySubscription($client);

        $response = $createVatlySubscription->execute('subscription_dummy_1');

        $this->assertInstanceOf(GetVatlySubscriptionResponse::class, $response);
        $this->assertEquals('subscription_dummy_1', $response->subscriptionId);
    }
}
