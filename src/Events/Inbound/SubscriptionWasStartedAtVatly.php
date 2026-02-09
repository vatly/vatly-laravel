<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events\Inbound;

use Vatly\Laravel\Models\Subscription;

class SubscriptionWasStartedAtVatly extends BaseAtVatlyEvent
{
    /**
     * The event name provided by the Vatly API.
     */
    public const VATLY_EVENT_NAME = 'subscription.started';

    public function __construct(
        public readonly string $customerId,
        public readonly string $subscriptionId,
        public readonly string $planId,
        public readonly string $type,
        public readonly string $name,
        public readonly int $quantity,
    ) {
        //
    }

    public static function fromWebhookCall(VatlyWebhookCallReceived $callReceived): self
    {
        return new self(
            customerId: $callReceived->object['data']['customerId'],
            subscriptionId: $callReceived->resourceId,
            planId: $callReceived->object['data']['subscriptionPlanId'],
            type: self::resolveSubscriptionType($callReceived),
            name: $callReceived->object['data']['name'],
            quantity: $callReceived->object['data']['quantity'],
        );
    }

    /**
     * Resolve the subscription type from metadata or fall back to default.
     */
    private static function resolveSubscriptionType(VatlyWebhookCallReceived $callReceived): string
    {
        $metadata = $callReceived->object['metadata'] ?? [];
        $vatlyLaravelMeta = $metadata['vatly_laravel'] ?? [];

        return $vatlyLaravelMeta['subscription_type'] ?? Subscription::DEFAULT_TYPE;
    }
}
