<?php

declare(strict_types=1);

namespace Vatly\Laravel\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Vatly\Actions\CancelSubscription;
use Vatly\Actions\SwapSubscriptionPlan;
use Vatly\Contracts\BillableInterface;
use Vatly\Contracts\SubscriptionInterface;
use Vatly\Events\SubscriptionCanceledImmediately;
use Vatly\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Events\SubscriptionStarted;
use Vatly\Exceptions\FeatureUnavailableException;
use Vatly\Laravel\Repositories\EloquentCustomerRepository;

/**
 * @property BillableInterface $owner
 * @property string $type
 * @property string $plan_id
 * @property string $vatly_id
 * @property string $name
 * @property int $quantity
 * @property Carbon|null $ends_at
 *
 * @method static create(array $array)
 * @method static where(string $column, mixed $value)
 */
class Subscription extends Model implements SubscriptionInterface
{
    public const DEFAULT_TYPE = 'default';

    protected $table = 'vatly_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    // SubscriptionInterface implementation

    public function getVatlyId(): string
    {
        return $this->vatly_id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPlanId(): string
    {
        return $this->plan_id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getEndsAt(): ?DateTimeInterface
    {
        return $this->ends_at;
    }

    public function getOwner(): BillableInterface
    {
        return $this->owner;
    }

    public function isCancelled(): bool
    {
        return $this->ends_at !== null;
    }

    public function isOnGracePeriod(): bool
    {
        return $this->isCancelled() && $this->ends_at?->isFuture();
    }

    public function isActive(): bool
    {
        return !$this->isCancelled() || $this->isOnGracePeriod();
    }

    // Legacy method aliases for backward compatibility

    public function cancelled(): bool
    {
        return $this->isCancelled();
    }

    public function onGracePeriod(): bool
    {
        return $this->isOnGracePeriod();
    }

    public function active(): bool
    {
        return $this->isActive();
    }

    // Business logic

    public function swap(string $type, string $planId, array $options = []): self
    {
        /** @var SwapSubscriptionPlan $action */
        $action = app()->make(SwapSubscriptionPlan::class);
        $response = $action->execute($this->vatly_id, $planId, $options);

        $this->update([
            'type' => $type,
            'plan_id' => $response->subscriptionPlanId,
            'quantity' => $response->quantity,
        ]);

        return $this;
    }

    public function swapAndInvoice(): self
    {
        throw FeatureUnavailableException::notImplementedOnSdk();
    }

    public function resume(): self
    {
        throw FeatureUnavailableException::notImplementedOnApi();
    }

    public function updatePaymentMethodUrl(): string
    {
        throw FeatureUnavailableException::notImplementedOnSdk();
    }

    public function sync(): self
    {
        throw FeatureUnavailableException::notImplementedOnSdk();
    }

    /**
     * Create a subscription from a webhook event.
     */
    public static function createFromWebhookEvent(SubscriptionStarted $event, BillableInterface $owner): self
    {
        return self::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'vatly_id' => $event->subscriptionId,
            'plan_id' => $event->planId,
            'name' => $event->name,
            'type' => $event->type,
            'quantity' => $event->quantity,
        ]);
    }

    /**
     * Cancel the subscription at Vatly.
     */
    public function cancel(): void
    {
        app()->make(CancelSubscription::class)->execute($this->vatly_id);
    }

    /**
     * Handle immediate cancellation from webhook.
     */
    public static function handleImmediateCancellation(SubscriptionCanceledImmediately $event): self
    {
        $subscription = self::where('vatly_id', $event->subscriptionId)->firstOrFail();
        $subscription->update(['ends_at' => Carbon::now()]);

        return $subscription;
    }

    /**
     * Handle grace period cancellation from webhook.
     */
    public static function handleGracePeriodCancellation(SubscriptionCanceledWithGracePeriod $event): self
    {
        $subscription = self::where('vatly_id', $event->subscriptionId)->firstOrFail();
        $subscription->update(['ends_at' => $event->endsAt]);

        return $subscription;
    }
}
