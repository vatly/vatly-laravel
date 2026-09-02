<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Carbon\Carbon;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\StoreSubscriptionData;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Laravel\Models\Subscription;
use Vatly\Laravel\VatlyConfig;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        private readonly VatlyConfig $config,
    ) {
        //
    }

    public function findByVatlyId(string $vatlyId): ?SubscriptionInterface
    {
        return Subscription::where('vatly_id', $vatlyId)->first();
    }

    public function store(StoreSubscriptionData $data): SubscriptionInterface
    {
        $attrs = [
            'vatly_id' => $data->vatlyId,
            'customer_id' => $data->customerId,
            'type' => $data->type,
            'plan_id' => $data->planId,
            'name' => $data->name,
            'quantity' => $data->quantity,
            'mandate_method' => $data->mandate?->method,
            'mandate_masked_identifier' => $data->mandate?->maskedIdentifier,
            'testmode' => $data->testmode,
        ];

        if ($data->hostCustomerId !== null) {
            $model = $this->config->getBillableModel();
            $attrs['owner_type'] = (new $model)->getMorphClass();
            $attrs['owner_id'] = $data->hostCustomerId;
        }

        return Subscription::create($attrs);
    }

    public function update(SubscriptionInterface $subscription, UpdateSubscriptionData $data): SubscriptionInterface
    {
        if ($subscription instanceof Subscription) {
            if ($data->planId !== null) {
                $subscription->plan_id = $data->planId;
            }
            if ($data->name !== null) {
                $subscription->name = $data->name;
            }
            if ($data->quantity !== null) {
                $subscription->quantity = $data->quantity;
            }
            if ($data->endsAt !== null) {
                $subscription->ends_at = Carbon::instance($data->endsAt);
            } elseif ($data->clearEndsAt) {
                $subscription->ends_at = null;
            }
            // Why the subscription was canceled (payment_failure / merchant_request
            // / customer_request), set by the cancellation webhook reactions. Null
            // means "no change", mirroring the status convention on the DTO.
            if ($data->cancellationReason !== null) {
                $subscription->cancellation_reason = $data->cancellationReason;
            }
            // Mandate is an atomic value object: a non-null Mandate replaces
            // both columns in one write (preventing mixed local state like
            // "paypal / 4242" when switching from a card mandate to a paypal
            // mandate that has no identifier). clearMandate explicitly nulls
            // both. Null Mandate with clearMandate=false is no-op.
            if ($data->mandate !== null) {
                $subscription->mandate_method = $data->mandate->method;
                $subscription->mandate_masked_identifier = $data->mandate->maskedIdentifier;
            } elseif ($data->clearMandate) {
                $subscription->mandate_method = null;
                $subscription->mandate_masked_identifier = null;
            }
            $subscription->save();
        }

        return $subscription;
    }
}
