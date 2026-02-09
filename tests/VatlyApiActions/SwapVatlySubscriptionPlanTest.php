<?php

declare(strict_types=1);

namespace VatlyApiActions;

use Vatly\API\Endpoints\SubscriptionEndpoint;
use Vatly\API\Resources\Subscription;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\VatlyApiClientWithReplacedEndpoint;
use Vatly\Laravel\VatlyApiActions\SwapVatlySubscriptionPlan;
use Vatly\Laravel\VatlyApiActions\SwapVatlySubscriptionPlanResponse;

class SwapVatlySubscriptionPlanTest extends BaseTestCase
{
    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(SwapVatlySubscriptionPlan::class, new SwapVatlySubscriptionPlan(vatlyApiClient: new VatlyApiClient));
    }

    /** @test */
    public function it_executes()
    {
        $vatlySubscriptionApiResponse = new Subscription(new VatlyApiClient());
        $vatlySubscriptionApiResponse->id = 'subscription_dummy_1';
        $vatlySubscriptionApiResponse->quantity = 1;
        $vatlySubscriptionApiResponse->subscriptionPlanId = 'subscription_plan_new_foo_bar';

        $mockSubscriptionsEndpoint = $this->createMock(SubscriptionEndpoint::class);
        $mockSubscriptionsEndpoint
            ->expects($this->once())
            ->method('swap')
            ->with('subscription_dummy_1', 'subscription_plan_new_foo_bar')
            ->willReturn($vatlySubscriptionApiResponse);
        $client = VatlyApiClientWithReplacedEndpoint::createAndReplaceEndpoint(
            'subscriptions',
            $mockSubscriptionsEndpoint,
        );

        $createVatlySubscription = new SwapVatlySubscriptionPlan($client);

        $response = $createVatlySubscription->execute('subscription_dummy_1', 'subscription_plan_new_foo_bar');

        $this->assertInstanceOf(SwapVatlySubscriptionPlanResponse::class, $response);
        $this->assertEquals('subscription_dummy_1', $response->subscriptionId);
        $this->assertEquals('subscription_plan_new_foo_bar', $response->subscriptionPlanId);
        $this->assertEquals(1, $response->quantity);
    }
}
