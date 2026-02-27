<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Events\Inbound;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\Laravel\Events\Inbound\SubscriptionWasStartedAtVatly;
use Vatly\Laravel\Events\Inbound\VatlyWebhookCallReceived;
use Vatly\Laravel\Models\Subscription;
use Vatly\Laravel\Tests\BaseTestCase;

class SubscriptionWasStartedAtVatlyTest extends BaseTestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_default_subscription_type_when_no_metadata_present(): void
    {
        $webhookCall = new VatlyWebhookCallReceived(
            eventName: 'subscription.started',
            resourceId: 'subscription_abc123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'customer_xyz',
                    'subscriptionPlanId' => 'plan_123',
                    'name' => 'Pro Plan',
                    'quantity' => 1,
                ],
            ],
            raisedAt: '2026-02-09T22:00:00Z',
            testmode: true,
        );

        $event = SubscriptionWasStartedAtVatly::fromWebhookCall($webhookCall);

        $this->assertSame(Subscription::DEFAULT_TYPE, $event->type);
    }

    /** @test */
    public function it_uses_subscription_type_from_metadata_when_present(): void
    {
        $webhookCall = new VatlyWebhookCallReceived(
            eventName: 'subscription.started',
            resourceId: 'subscription_abc123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'customer_xyz',
                    'subscriptionPlanId' => 'plan_123',
                    'name' => 'Pro Plan',
                    'quantity' => 1,
                ],
                'metadata' => [
                    'vatly_laravel' => [
                        'subscription_type' => 'premium',
                    ],
                ],
            ],
            raisedAt: '2026-02-09T22:00:00Z',
            testmode: true,
        );

        $event = SubscriptionWasStartedAtVatly::fromWebhookCall($webhookCall);

        $this->assertSame('premium', $event->type);
    }

    /** @test */
    public function it_falls_back_to_default_when_vatly_laravel_metadata_is_empty(): void
    {
        $webhookCall = new VatlyWebhookCallReceived(
            eventName: 'subscription.started',
            resourceId: 'subscription_abc123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'customer_xyz',
                    'subscriptionPlanId' => 'plan_123',
                    'name' => 'Pro Plan',
                    'quantity' => 1,
                ],
                'metadata' => [
                    'some_other_key' => 'value',
                ],
            ],
            raisedAt: '2026-02-09T22:00:00Z',
            testmode: true,
        );

        $event = SubscriptionWasStartedAtVatly::fromWebhookCall($webhookCall);

        $this->assertSame(Subscription::DEFAULT_TYPE, $event->type);
    }

    /** @test */
    public function it_extracts_all_fields_correctly(): void
    {
        $webhookCall = new VatlyWebhookCallReceived(
            eventName: 'subscription.started',
            resourceId: 'subscription_abc123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'customer_xyz',
                    'subscriptionPlanId' => 'plan_enterprise',
                    'name' => 'Enterprise Plan',
                    'quantity' => 5,
                ],
                'metadata' => [
                    'vatly_laravel' => [
                        'subscription_type' => 'team',
                    ],
                ],
            ],
            raisedAt: '2026-02-09T22:00:00Z',
            testmode: true,
        );

        $event = SubscriptionWasStartedAtVatly::fromWebhookCall($webhookCall);

        $this->assertSame('customer_xyz', $event->customerId);
        $this->assertSame('subscription_abc123', $event->subscriptionId);
        $this->assertSame('plan_enterprise', $event->planId);
        $this->assertSame('team', $event->type);
        $this->assertSame('Enterprise Plan', $event->name);
        $this->assertSame(5, $event->quantity);
    }
}
