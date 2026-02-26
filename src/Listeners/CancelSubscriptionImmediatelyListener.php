<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Laravel\Models\Subscription;

class CancelSubscriptionImmediatelyListener
{
    public function handle(SubscriptionCanceledImmediately $event): Subscription
    {
        return Subscription::handleImmediateCancellation($event);
    }
}
