<?php

declare(strict_types=1);

use Vatly\Laravel\Events\Inbound\SubscriptionWasStartedAtVatly;
use Vatly\Laravel\Events\Inbound\VatlyWebhookCallReceived;
use Vatly\Laravel\Models\Subscription;

describe('fromWebhookCall', function () {
    test('it uses default subscription type when no metadata present', function () {
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

        expect($event->type)->toBe(Subscription::DEFAULT_TYPE);
    });

    test('it uses subscription type from metadata when present', function () {
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

        expect($event->type)->toBe('premium');
    });

    test('it falls back to default when vatly_laravel metadata is empty', function () {
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

        expect($event->type)->toBe(Subscription::DEFAULT_TYPE);
    });

    test('it extracts all fields correctly', function () {
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

        expect($event->customerId)->toBe('customer_xyz')
            ->and($event->subscriptionId)->toBe('subscription_abc123')
            ->and($event->planId)->toBe('plan_enterprise')
            ->and($event->type)->toBe('team')
            ->and($event->name)->toBe('Enterprise Plan')
            ->and($event->quantity)->toBe(5);
    });
});
