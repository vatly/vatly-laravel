<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Models;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\Laravel\Models\VatlyWebhookCall;
use Vatly\Laravel\Tests\BaseTestCase;

class VatlyWebhookCallTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_model_prune_deletes_rows_past_the_default_retention(): void
    {
        $this->makeCall('webhook_event_old', now()->subDays(31));
        $this->makeCall('webhook_event_recent', now()->subDays(29));

        $this->artisan('model:prune', ['--model' => [VatlyWebhookCall::class]])->assertSuccessful();

        $this->assertSame(['webhook_event_recent'], VatlyWebhookCall::pluck('vatly_id')->all());
    }

    public function test_a_retention_of_zero_keeps_every_row(): void
    {
        config(['vatly.webhook_calls_retention_days' => 0]);
        $this->makeCall('webhook_event_old', now()->subDays(400));

        $this->artisan('model:prune', ['--model' => [VatlyWebhookCall::class]])->assertSuccessful();

        $this->assertSame(1, VatlyWebhookCall::count());
        $this->assertNull($this->scheduledPrune());
    }

    public function test_pruning_is_scheduled_daily_by_default(): void
    {
        $event = $this->scheduledPrune();

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    private function scheduledPrune(): ?ScheduledEvent
    {
        $this->app->forgetInstance(Schedule::class);

        return collect($this->app->make(Schedule::class)->events())
            ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'model:prune')
                && str_contains((string) $event->command, 'VatlyWebhookCall'));
    }

    private function makeCall(string $vatlyId, \DateTimeInterface $createdAt): void
    {
        $call = new VatlyWebhookCall([
            'vatly_id' => $vatlyId,
            'resource' => 'webhook_event',
            'event_name' => 'order.paid',
            'entity_type' => 'order',
            'entity_id' => 'order_1',
            'testmode' => true,
            'vatly_created_at' => $createdAt,
            'object' => [],
        ]);
        $call->created_at = $createdAt;
        $call->save();
    }
}
