<?php

declare(strict_types=1);

use Carbon\Carbon;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\Responses\GetSubscriptionResponse;
use Vatly\Laravel\Models\Subscription;

beforeEach(function () {
    // Create a mock subscription in the database
    $this->subscription = new Subscription([
        'vatly_id' => 'subscription_test123',
        'plan_id' => 'plan_old',
        'name' => 'Old Plan',
        'type' => 'default',
        'quantity' => 1,
    ]);
});

describe('sync', function () {
    test('it updates local subscription with fresh data from Vatly', function () {
        $mockAction = Mockery::mock(GetSubscription::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with('subscription_test123')
            ->andReturn(new GetSubscriptionResponse(
                subscriptionId: 'subscription_test123',
                planId: 'plan_new',
                name: 'New Plan',
                quantity: 5,
                status: 'active',
            ));

        app()->instance(GetSubscription::class, $mockAction);

        // Mock the update method since we don't have a real database
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->vatly_id = 'subscription_test123';
        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['plan_id'] === 'plan_new'
                    && $data['name'] === 'New Plan'
                    && $data['quantity'] === 5;
            }));

        $result = $subscription->sync();

        expect($result)->toBe($subscription);
    });

    test('it syncs ends_at when subscription is ended', function () {
        $endedAt = '2026-03-01T00:00:00Z';

        $mockAction = Mockery::mock(GetSubscription::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn(new GetSubscriptionResponse(
                subscriptionId: 'subscription_test123',
                planId: 'plan_123',
                name: 'Plan',
                quantity: 1,
                status: 'canceled',
                endedAt: $endedAt,
            ));

        app()->instance(GetSubscription::class, $mockAction);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->vatly_id = 'subscription_test123';
        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) use ($endedAt) {
                return $data['ends_at'] instanceof Carbon
                    && $data['ends_at']->toIso8601String() === Carbon::parse($endedAt)->toIso8601String();
            }));

        $subscription->sync();
    });

    test('it syncs trial_ends_at when present', function () {
        $trialEndAt = '2026-02-15T00:00:00Z';

        $mockAction = Mockery::mock(GetSubscription::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn(new GetSubscriptionResponse(
                subscriptionId: 'subscription_test123',
                planId: 'plan_123',
                name: 'Trial Plan',
                quantity: 1,
                status: 'trial',
                trialEndAt: $trialEndAt,
            ));

        app()->instance(GetSubscription::class, $mockAction);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->vatly_id = 'subscription_test123';
        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) {
                return isset($data['trial_ends_at']) && $data['trial_ends_at'] instanceof Carbon;
            }));

        $subscription->sync();
    });
});

afterEach(function () {
    Mockery::close();
});
