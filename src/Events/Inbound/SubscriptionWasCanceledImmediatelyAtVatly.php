<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events\Inbound;

class SubscriptionWasCanceledImmediatelyAtVatly extends BaseAtVatlyEvent
{
    /**
     * The event name provided by the Vatly API.
     */
    const VATLY_EVENT_NAME = 'subscription.canceled_immediately';

    public function __construct(
        public readonly string $customerId,
        public readonly string $subscriptionId,
    ) {
        //
    }

    public static function fromWebhookCall(VatlyWebhookCallReceived $callReceived): BaseAtVatlyEvent
    {
        return new self(
            customerId: $callReceived->object['data']['customerId'],
            subscriptionId: $callReceived->resourceId,
        );
    }
}
