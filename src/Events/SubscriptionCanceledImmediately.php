<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Vatly\Fluent\Events\SubscriptionCanceledImmediately as FluentSubscriptionCanceledImmediately;

/**
 * Event dispatched when a subscription is canceled immediately at Vatly.
 *
 * This is a Laravel wrapper around the Fluent event for namespace consistency.
 */
class SubscriptionCanceledImmediately extends FluentSubscriptionCanceledImmediately
{
    // Inherits all properties and methods from Fluent event
}
