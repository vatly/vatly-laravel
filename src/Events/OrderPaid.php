<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Vatly\Fluent\Events\OrderPaid as FluentOrderPaid;

/**
 * Event dispatched when an order is paid at Vatly.
 *
 * This is a Laravel wrapper around the Fluent event for namespace consistency.
 */
class OrderPaid extends FluentOrderPaid
{
    // Inherits all properties and methods from Fluent event
}
