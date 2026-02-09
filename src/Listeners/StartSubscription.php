<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Laravel\Events\Inbound\SubscriptionWasStartedAtVatly;
use Vatly\Laravel\Models\Subscription;

class StartSubscription
{
    public function handle(SubscriptionWasStartedAtVatly $event): Subscription
    {
        return Subscription::createFromSubscriptionWasStartedAtVatly($event);
    }
}
