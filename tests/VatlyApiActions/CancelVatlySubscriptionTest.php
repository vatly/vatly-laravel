<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\VatlyApiActions;

use Vatly\API\Endpoints\SubscriptionEndpoint;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\CancelVatlySubscription;

class CancelVatlySubscriptionTest extends BaseTestCase
{
    /** @test */
    public function it_initiates(): void
    {
        $this->assertInstanceOf(CancelVatlySubscription::class, new CancelVatlySubscription(vatlyApiClient: new VatlyApiClient));
    }

    /** @test */
    public function it_executes(): void
    {
        $mockSubscriptionsEndpoint = $this->createMock(SubscriptionEndpoint::class);
        $mockSubscriptionsEndpoint
            ->expects($this->once())
            ->method('cancel')
            ->with('subscription_dummy_1')
            ->willReturn(null);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'subscriptions',
            $mockSubscriptionsEndpoint,
        );

        $cancelVatlySubscription = new CancelVatlySubscription($client);

        $response = $cancelVatlySubscription->execute('subscription_dummy_1');

        $this->assertNull($response);
    }
}
