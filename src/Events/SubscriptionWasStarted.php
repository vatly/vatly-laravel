<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Illuminate\Database\Eloquent\Model;

class SubscriptionWasStarted extends BaseVatlyEvent
{
    public function __construct(
        public Model $subscription,
    ) {
        //
    }
}
