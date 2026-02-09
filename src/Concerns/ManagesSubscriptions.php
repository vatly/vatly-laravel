<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vatly\Laravel\Models\Subscription;

trait ManagesSubscriptions
{
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'owner')->orderByDesc('created_at');
    }

    public function subscription(string $type = Subscription::DEFAULT_TYPE): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    public function subscribed(string $type = Subscription::DEFAULT_TYPE): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->active()) {
            return false;
        }

        return true;
    }
}
