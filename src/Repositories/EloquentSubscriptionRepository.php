<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\Contracts\BillableInterface;
use Vatly\Contracts\SubscriptionInterface;
use Vatly\Contracts\SubscriptionRepositoryInterface;
use Vatly\Laravel\Models\Subscription;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findByVatlyId(string $vatlyId): ?SubscriptionInterface
    {
        return Subscription::where('vatly_id', $vatlyId)->first();
    }

    public function findByOwnerAndType(BillableInterface $owner, string $type): ?SubscriptionInterface
    {
        return Subscription::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('type', $type)
            ->first();
    }

    /**
     * @return SubscriptionInterface[]
     */
    public function findAllByOwner(BillableInterface $owner): array
    {
        return Subscription::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->get()
            ->all();
    }

    public function ownerHasActiveSubscription(BillableInterface $owner, string $type): bool
    {
        $subscription = $this->findByOwnerAndType($owner, $type);

        return $subscription !== null && $subscription->isActive();
    }

    public function create(array $attributes): SubscriptionInterface
    {
        return Subscription::create($attributes);
    }

    public function update(SubscriptionInterface $subscription, array $attributes): SubscriptionInterface
    {
        if ($subscription instanceof Subscription) {
            $subscription->update($attributes);
        }

        return $subscription;
    }
}
