<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\Fluent\Contracts\OrderLineInterface;
use Vatly\Fluent\Contracts\OrderLineRepositoryInterface;
use Vatly\Fluent\Data\OrderLineData;
use Vatly\Laravel\Models\OrderLine;

class EloquentOrderLineRepository implements OrderLineRepositoryInterface
{
    /**
     * @return OrderLineInterface[]
     */
    public function listForOrder(string $vatlyOrderId): array
    {
        return OrderLine::where('order_vatly_id', $vatlyOrderId)->get()->all();
    }

    /**
     * @return OrderLineInterface[]
     */
    public function listForSubscription(string $subscriptionId): array
    {
        return OrderLine::where('product_type', 'subscription')
            ->where('product_id', $subscriptionId)
            ->get()
            ->all();
    }

    public function store(OrderLineData $data, string $vatlyOrderId): ?OrderLineInterface
    {
        return OrderLine::create([
            'order_vatly_id' => $vatlyOrderId,
            'vatly_id' => $data->vatlyId,
            'description' => $data->description,
            'quantity' => $data->quantity,
            'base_price' => $data->basePrice,
            'total' => $data->total,
            'subtotal' => $data->subtotal,
            'tax_summary' => $data->taxSummary?->toArray(),
            'product_type' => $data->productType,
            'product_id' => $data->productId,
        ]);
    }
}
