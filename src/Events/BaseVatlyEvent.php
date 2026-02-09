<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BaseVatlyEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
}
