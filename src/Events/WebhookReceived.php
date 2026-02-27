<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Vatly\Fluent\Events\WebhookReceived as FluentWebhookReceived;

/**
 * Event dispatched when any webhook is received from Vatly.
 *
 * This is a Laravel wrapper around the Fluent event for namespace consistency.
 */
class WebhookReceived extends FluentWebhookReceived
{
    // Inherits all properties and methods from Fluent event
}
