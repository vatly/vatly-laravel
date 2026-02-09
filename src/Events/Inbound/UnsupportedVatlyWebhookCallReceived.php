<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events\Inbound;

class UnsupportedVatlyWebhookCallReceived extends BaseAtVatlyEvent
{
    protected function __construct(
        public readonly string $eventName,
        public readonly string $resourceId,
        public readonly string $resourceName,
        public readonly array $object,
        public readonly string $raisedAt,
        public readonly bool $testmode,
    ) {
        //
    }

    public static function fromWebhookCall(VatlyWebhookCallReceived $callReceived): BaseAtVatlyEvent
    {
        return new self(
            eventName: $callReceived->eventName,
            resourceId: $callReceived->resourceId,
            resourceName: $callReceived->resourceName,
            object: $callReceived->object,
            raisedAt: $callReceived->raisedAt,
            testmode: $callReceived->testmode,
        );
    }
}
