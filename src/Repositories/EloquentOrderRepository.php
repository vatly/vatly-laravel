<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Laravel\Models\Order;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findByVatlyId(string $vatlyId): ?OrderInterface
    {
        return Order::where('vatly_id', $vatlyId)->first();
    }

    /**
     * @return OrderInterface[]
     */
    public function findAllByOwner(BillableInterface $owner): array
    {
        return Order::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): OrderInterface
    {
        return Order::create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(OrderInterface $order, array $attributes): OrderInterface
    {
        if ($order instanceof Order) {
            $order->update($attributes);
        }

        return $order;
    }
}
