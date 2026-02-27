<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use DateTimeInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Laravel\Models\VatlyWebhookCall;

class EloquentWebhookCallRepository implements WebhookCallRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function record(
        string $eventName,
        string $resourceId,
        string $resourceName,
        array $payload,
        DateTimeInterface $raisedAt,
        bool $testmode,
        ?string $vatlyCustomerId = null,
    ): void {
        VatlyWebhookCall::create([
            'event_name' => $eventName,
            'resource_id' => $resourceId,
            'resource_name' => $resourceName,
            'object' => $payload,
            'raised_at' => $raisedAt,
            'testmode' => $testmode,
            'vatly_customer_id' => $vatlyCustomerId,
        ]);
    }

    public function cleanUp(int $days = 7): int
    {
        return VatlyWebhookCall::cleanUp($days);
    }
}
