<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod as FluentSubscriptionCanceledWithGracePeriod;

/**
 * Event dispatched when a subscription is canceled with a grace period at Vatly.
 *
 * This is a Laravel wrapper around the Fluent event for namespace consistency.
 */
class SubscriptionCanceledWithGracePeriod extends FluentSubscriptionCanceledWithGracePeriod
{
    // Inherits all properties and methods from Fluent event
}
