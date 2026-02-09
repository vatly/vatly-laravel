<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Laravel\Models\Subscription;

class CancelSubscriptionWithGracePeriodListener
{
    public function handle(SubscriptionCanceledWithGracePeriod $event): Subscription
    {
        return Subscription::handleGracePeriodCancellation($event);
    }
}
