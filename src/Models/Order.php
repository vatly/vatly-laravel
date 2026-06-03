<?php

declare(strict_types=1);

namespace Vatly\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\OrderHandle;
use Vatly\Fluent\Vatly;

/**
 * @property string $vatly_id
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string|null $customer_id The Vatly customer id (cus_…), populated even for anonymous flows.
 * @property string $status
 * @property int $total
 * @property int|null $subtotal
 * @property array<int, array{rate: array{name: string, percentage: float, taxablePercentage: float}, amount: int, currency: string}>|null $tax_summary
 * @property string $currency
 * @property string|null $invoice_number
 * @property string|null $payment_method
 *
 * @method static create(array<string, mixed> $array)
 * @method static where(string $column, mixed $value)
 */
class Order extends Model implements OrderInterface
{
    protected $table = 'vatly_orders';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tax_summary' => 'array',
    ];

    /**
     * @return MorphTo<Model, Order>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    /**
     * Refunds issued against this order.
     *
     * Linked on the Vatly order id (`original_order_id` → `vatly_id`) rather
     * than the local primary key, since refund rows reference the order by its
     * Vatly id.
     *
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'original_order_id', 'vatly_id')
            ->orderByDesc('created_at');
    }

    /**
     * Chargebacks raised against this order.
     *
     * Linked on the Vatly order id (`original_order_id` → `vatly_id`), mirroring
     * {@see self::refunds()}.
     *
     * @return HasMany<Chargeback, $this>
     */
    public function chargebacks(): HasMany
    {
        return $this->hasMany(Chargeback::class, 'original_order_id', 'vatly_id')
            ->orderByDesc('created_at');
    }

    // OrderInterface implementation

    public function getVatlyId(): string
    {
        return $this->vatly_id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoice_number;
    }

    public function getTotal(): int
    {
        return (int) $this->total;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->payment_method;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Get the hosted invoice URL for this order.
     *
     * Lets consumers iterate the orders relation and call `invoiceUrl()`
     * on each model directly. Internally delegates to the framework-agnostic
     * {@see OrderHandle::invoiceUrl()}.
     */
    public function invoiceUrl(): ?string
    {
        return app(Vatly::class)->order($this)->invoiceUrl();
    }

    // Reversal read surface.
    //
    // The order's own `status` stays terminal `paid` even after a refund or
    // chargeback; reversal progress is read live from the Vatly API rather than
    // synthesized into a local status. These delegate to the framework-agnostic
    // {@see OrderHandle} helpers (which memoize a single API fetch per call),
    // so consumers can iterate the orders relation and ask each model directly.

    /**
     * Subtotal (net of tax, in integer cents) that has been reversed —
     * refunded and/or charged back — per the live API order.
     */
    public function reversedSubtotal(): int
    {
        return app(Vatly::class)->order($this)->reversedSubtotal();
    }

    /**
     * Subtotal (net of tax, in integer cents) still available to reverse per
     * the live API order.
     */
    public function refundableSubtotal(): int
    {
        return app(Vatly::class)->order($this)->refundableSubtotal();
    }

    /**
     * Whether any of the order's subtotal has been reversed.
     */
    public function isReversed(): bool
    {
        return app(Vatly::class)->order($this)->isReversed();
    }

    /**
     * Whether the order is reversed but not in full.
     */
    public function isPartiallyReversed(): bool
    {
        return app(Vatly::class)->order($this)->isPartiallyReversed();
    }

    /**
     * Whether the order's full subtotal has been reversed.
     */
    public function isFullyReversed(): bool
    {
        return app(Vatly::class)->order($this)->isFullyReversed();
    }
}
