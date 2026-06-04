<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\Fluent\Contracts\ChargebackInterface;
use Vatly\Fluent\Contracts\ChargebackRepositoryInterface;
use Vatly\Fluent\Data\StoreChargebackData;
use Vatly\Fluent\Data\UpdateChargebackData;
use Vatly\Laravel\Models\Chargeback;
use Vatly\Laravel\Repositories\Concerns\SerializesTaxSummary;
use Vatly\Laravel\VatlyConfig;

class EloquentChargebackRepository implements ChargebackRepositoryInterface
{
    use SerializesTaxSummary;

    public function __construct(
        private readonly VatlyConfig $config,
    ) {
        //
    }

    public function findByVatlyId(string $vatlyId): ?ChargebackInterface
    {
        return Chargeback::where('vatly_id', $vatlyId)->first();
    }

    /**
     * @return ChargebackInterface[]
     */
    public function listForCustomer(string $customerId): array
    {
        return Chargeback::where('customer_id', $customerId)->get()->all();
    }

    /**
     * @return ChargebackInterface[]
     */
    public function listForOrder(string $vatlyOrderId): array
    {
        return Chargeback::where('original_order_id', $vatlyOrderId)->get()->all();
    }

    public function store(StoreChargebackData $data): ?ChargebackInterface
    {
        $attrs = [
            'vatly_id' => $data->vatlyId,
            'customer_id' => $data->customerId,
            'original_order_id' => $data->originalOrderId,
            'status' => $data->status,
            'total' => $data->total,
            'subtotal' => $data->subtotal,
            'tax_summary' => $this->serializeTaxSummary($data->taxSummary),
            'currency' => $data->currency,
            'reason' => $data->reason,
            'testmode' => $data->testmode,
        ];

        if ($data->hostCustomerId !== null) {
            $model = $this->config->getBillableModel();
            $attrs['owner_type'] = (new $model)->getMorphClass();
            $attrs['owner_id'] = $data->hostCustomerId;
        }

        return Chargeback::create($attrs);
    }

    public function update(ChargebackInterface $chargeback, UpdateChargebackData $data): ChargebackInterface
    {
        if ($chargeback instanceof Chargeback) {
            if ($data->status !== null) {
                $chargeback->status = $data->status;
            }
            if ($data->total !== null) {
                $chargeback->total = $data->total;
            }
            if ($data->subtotal !== null) {
                $chargeback->subtotal = $data->subtotal;
            }
            if ($data->taxSummary !== null) {
                $chargeback->tax_summary = $this->serializeTaxSummary($data->taxSummary);
            }
            if ($data->currency !== null) {
                $chargeback->currency = $data->currency;
            }
            if ($data->reason !== null) {
                $chargeback->reason = $data->reason;
            }
            $chargeback->save();
        }

        return $chargeback;
    }
}
