<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events\Inbound;

//use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

//use Illuminate\Queue\SerializesModels;

class VatlyWebhookCallReceived
{
    use Dispatchable;
    //use InteractsWithSockets;
    //use SerializesModels;

    public function __construct(
        public readonly string $eventName,
        public readonly string $resourceId,
        public readonly string $resourceName,
        public readonly array $object,
        public readonly string $raisedAt,
        public readonly bool $testmode,
    ) {
        //
    }

    public function toArray(): array
    {
        return [
            'eventName' => $this->eventName,
            'resourceId' => $this->resourceId,
            'resourceName' => $this->resourceName,
            'object' => $this->object,
            'raisedAt' => $this->raisedAt,
            'testmode' => $this->testmode,
        ];
    }
}
