<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Vatly\Fluent\Events\SubscriptionStarted as FluentSubscriptionStarted;

/**
 * Event dispatched when a subscription starts at Vatly.
 *
 * This is a Laravel wrapper around the Fluent event for namespace consistency.
 */
class SubscriptionStarted extends FluentSubscriptionStarted
{
    // Inherits all properties and methods from Fluent event
}
