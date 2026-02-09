<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Listeners;

use Illuminate\Support\Facades\Event;
use Vatly\Contracts\EventDispatcherInterface;
use Vatly\Events\SubscriptionStarted;
use Vatly\Events\UnsupportedWebhookReceived;
use Vatly\Events\WebhookReceived;
use Vatly\Laravel\Listeners\CascadeVatlyWebhookEvents;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Webhooks\WebhookEventFactory;

class CascadeVatlyWebhookEventsTest extends BaseTestCase
{
    private CascadeVatlyWebhookEvents $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = new CascadeVatlyWebhookEvents(
            new WebhookEventFactory(),
            $this->createMockDispatcher(),
        );
    }

    /** @test */
    public function it_cascades_unsupported_events()
    {
        Event::fake();
        $webhook = new WebhookReceived(
            eventName: 'some.unknown.event',
            resourceId: 'some_unknown_resource_id',
            resourceName: 'something_unknown',
            object: ['foo' => 'bar'],
            raisedAt: '2024-07-26T20:34:15+00:00',
            testmode: true,
        );

        $result = $this->listener->handle($webhook);

        $this->assertInstanceOf(UnsupportedWebhookReceived::class, $result);
        $this->assertEquals('some.unknown.event', $result->eventName);
    }

    /** @test */
    public function it_cascades_subscription_started_events()
    {
        Event::fake();

        $webhook = new WebhookReceived(
            eventName: SubscriptionStarted::VATLY_EVENT_NAME,
            resourceId: 'subscription_2gN9feZDAKXzKtLB4kQtH',
            resourceName: 'subscription',
            object: [
                "data" => [
                    "id" => "subscription_2gN9feZDAKXzKtLB4kQtH",
                    "name" => "Premium Plan",
                    "status" => "active",
                    "metadata" => [],
                    "quantity" => 1,
                    "resource" => "subscription",
                    "testmode" => true,
                    "customerId" => "customer_8GTuKcdBocyq7BhJwzMtH",
                    "merchantId" => "merchant_UTxC7VPUuro7chNbJgDtH",
                    "description" => "Sandorian Glass Ultimate (yearly)",
                    "subscriptionPlanId" => "subscription_plan_FcPxdZFmQYrsGWJ9ugFtH"
                ],
                "changed" => [
                    "status" => "active"
                ],
                "related" => []
            ],
            raisedAt: '2024-07-26T20:34:15+00:00',
            testmode: true,
        );

        $result = $this->listener->handle($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $result);
        $this->assertEquals('customer_8GTuKcdBocyq7BhJwzMtH', $result->customerId);
        $this->assertEquals('subscription_2gN9feZDAKXzKtLB4kQtH', $result->subscriptionId);
        $this->assertEquals('subscription_plan_FcPxdZFmQYrsGWJ9ugFtH', $result->planId);
    }

    private function createMockDispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            public function dispatch(object $event): void
            {
                // No-op for testing
            }
        };
    }
}
