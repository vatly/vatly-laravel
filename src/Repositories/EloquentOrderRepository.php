<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderLineRepositoryInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Laravel\Models\Order;
use Vatly\Laravel\Repositories\Concerns\SerializesTaxSummary;
use Vatly\Laravel\VatlyConfig;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    use SerializesTaxSummary;

    public function __construct(
        private readonly VatlyConfig $config,
        private readonly OrderLineRepositoryInterface $orderLines,
    ) {
        //
    }

    public function findByVatlyId(string $vatlyId): ?OrderInterface
    {
        return Order::where('vatly_id', $vatlyId)->first();
    }

    public function store(StoreOrderData $data): OrderInterface
    {
        $attrs = [
            'vatly_id' => $data->vatlyId,
            'customer_id' => $data->customerId,
            'status' => $data->status,
            'total' => $data->total,
            'subtotal' => $data->subtotal,
            'tax_summary' => $this->serializeTaxSummary($data->taxSummary),
            'currency' => $data->currency,
            'invoice_number' => $data->invoiceNumber,
            'payment_method' => $data->paymentMethod,
        ];

        if ($data->hostCustomerId !== null) {
            $model = $this->config->getBillableModel();
            $attrs['owner_type'] = (new $model)->getMorphClass();
            $attrs['owner_id'] = $data->hostCustomerId;
        }

        $order = Order::create($attrs);

        // Lines are immutable once paid, so they're only written here on the
        // initial store — the update path leaves existing lines as-is.
        foreach ($data->lines as $line) {
            $this->orderLines->store($line, $data->vatlyId);
        }

        return $order;
    }

    public function update(OrderInterface $order, UpdateOrderData $data): OrderInterface
    {
        if ($order instanceof Order) {
            if ($data->status !== null) {
                $order->status = $data->status;
            }
            if ($data->total !== null) {
                $order->total = $data->total;
            }
            if ($data->subtotal !== null) {
                $order->subtotal = $data->subtotal;
            }
            if ($data->taxSummary !== null) {
                $order->tax_summary = $this->serializeTaxSummary($data->taxSummary);
            }
            if ($data->currency !== null) {
                $order->currency = $data->currency;
            }
            if ($data->invoiceNumber !== null) {
                $order->invoice_number = $data->invoiceNumber;
            }
            if ($data->paymentMethod !== null) {
                $order->payment_method = $data->paymentMethod;
            }
            $order->save();
        }

        return $order;
    }
}
