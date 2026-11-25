<?php

declare(strict_types=1);

namespace Vatly\Laravel\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $vatly_id
 * @property string $resource
 * @property string $event_name
 * @property string $entity_type
 * @property string $entity_id
 * @property bool $testmode
 * @property Carbon $vatly_created_at
 * @property ?string $vatly_customer_id
 * @property array<string, mixed> $object
 */
class VatlyWebhookCall extends Model
{
    use MassPrunable;

    public const DEFAULT_DAYS_TO_RETAIN = 7;

    protected $table = 'vatly_webhook_calls';

    protected $fillable = [
        'vatly_id',
        'resource',
        'event_name',
        'entity_type',
        'entity_id',
        'testmode',
        'vatly_created_at',
        'vatly_customer_id',
        'object',
    ];

    protected $casts = [
        'testmode' => 'boolean',
        'vatly_created_at' => 'datetime',
        'object' => 'array',
    ];

    /**
     * Rows older than `vatly.webhook_calls_retention_days`, which the package
     * deletes daily through `model:prune`. A retention of 0 or null keeps
     * every row.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('vatly.webhook_calls_retention_days');

        if ($days < 1) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<', Carbon::now()->subDays($days));
    }

    public static function cleanUp(int $daysToRetain = self::DEFAULT_DAYS_TO_RETAIN): int
    {
        return static::where('created_at', '<', Carbon::now()->subDays($daysToRetain))->delete();
    }
}
