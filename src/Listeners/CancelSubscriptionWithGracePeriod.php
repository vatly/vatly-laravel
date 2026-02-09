<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Laravel\Events\Inbound\SubscriptionWasCanceledWithGracePeriodAtVatly;
use Vatly\Laravel\Models\Subscription;

class CancelSubscriptionWithGracePeriod
{
    public function handle(SubscriptionWasCanceledWithGracePeriodAtVatly $event)
    {
        Subscription::handleSubscriptionWasCanceledWithGracePeriodAtVatly($event);
    }
}
