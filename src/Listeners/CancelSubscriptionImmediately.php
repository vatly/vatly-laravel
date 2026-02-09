<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Laravel\Events\Inbound\SubscriptionWasCanceledImmediatelyAtVatly;
use Vatly\Laravel\Models\Subscription;

class CancelSubscriptionImmediately
{
    public function handle(SubscriptionWasCanceledImmediatelyAtVatly $event): Subscription
    {
        return Subscription::handleSubscriptionWasCanceledImmediatelyAtVatly($event);
    }
}
