<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\API\Types\OrderLineData;
use Vatly\Fluent\Contracts\OrderLineInterface;
use Vatly\Fluent\Contracts\OrderLineRepositoryInterface;
use Vatly\Laravel\Models\OrderLine;
use Vatly\Laravel\Repositories\Concerns\SerializesTaxSummary;

class EloquentOrderLineRepository implements OrderLineRepositoryInterface
{
    use SerializesTaxSummary;

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
            'base_price' => $data->basePrice->toCents(),
            'total' => $data->total->toCents(),
            'subtotal' => $data->subtotal->toCents(),
            'tax_summary' => $this->serializeTaxSummary($data->taxSummary),
            'product_type' => $data->productType,
            'product_id' => $data->productId,
        ]);
    }
}
