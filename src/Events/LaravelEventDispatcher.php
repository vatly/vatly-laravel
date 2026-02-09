<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Illuminate\Support\Facades\Event;
use Vatly\Contracts\EventDispatcherInterface;

class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        Event::dispatch($event);
    }
}
