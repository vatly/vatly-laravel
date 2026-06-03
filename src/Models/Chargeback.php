<?php

declare(strict_types=1);

namespace Vatly\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Vatly\Fluent\Contracts\ChargebackInterface;

/**
 * @property string $vatly_id
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string|null $customer_id The Vatly customer id (cus_…).
 * @property string $original_order_id The Vatly id of the order the chargeback was raised against.
 * @property string $status
 * @property int $total
 * @property int|null $subtotal
 * @property array<int, array{rate: array{name: string, percentage: float, taxablePercentage: float}, amount: int, currency: string}>|null $tax_summary
 * @property string $currency
 * @property string|null $reason
 *
 * @method static create(array<string, mixed> $array)
 * @method static where(string $column, mixed $value)
 */
class Chargeback extends Model implements ChargebackInterface
{
    protected $table = 'vatly_chargebacks';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tax_summary' => 'array',
    ];

    /**
     * @return MorphTo<Model, Chargeback>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    // ChargebackInterface implementation

    public function getVatlyId(): string
    {
        return $this->vatly_id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotal(): int
    {
        return (int) $this->total;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getOriginalOrderId(): string
    {
        return $this->original_order_id;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function isReversed(): bool
    {
        // Dispute lifecycle: pending, accepted, rejected, evidence_submitted,
        // won, lost. A reversal — funds returned to the merchant — is "won".
        return $this->status === 'won';
    }
}
