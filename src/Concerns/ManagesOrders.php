<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vatly\Laravel\Models\Order;

trait ManagesOrders
{
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'owner')->orderByDesc('created_at');
    }
}
