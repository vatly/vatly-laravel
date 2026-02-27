<?php

declare(strict_types=1);

use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Laravel\Models\Subscription;

describe('swapAndInvoice', function () {
    test('it swaps plan with immediate invoicing', function () {
        $apiSubscription = Mockery::mock(ApiSubscription::class);
        $apiSubscription->id = 'subscription_test123';
        $apiSubscription->subscriptionPlanId = 'plan_premium';
        $apiSubscription->quantity = 1;

        $mockAction = Mockery::mock(SwapSubscriptionPlan::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(
                'subscription_test123',
                'plan_premium',
                Mockery::on(function ($options) {
                    return $options['applyImmediately'] === true
                        && $options['invoiceImmediately'] === true;
                })
            )
            ->andReturn($apiSubscription);

        app()->instance(SwapSubscriptionPlan::class, $mockAction);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->vatly_id = 'subscription_test123';
        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['type'] === 'premium'
                    && $data['plan_id'] === 'plan_premium'
                    && $data['quantity'] === 1;
            }));

        $result = $subscription->swapAndInvoice('premium', 'plan_premium');

        expect($result)->toBe($subscription);
    });

    test('it passes additional options while forcing immediate flags', function () {
        $apiSubscription = Mockery::mock(ApiSubscription::class);
        $apiSubscription->id = 'subscription_test123';
        $apiSubscription->subscriptionPlanId = 'plan_enterprise';
        $apiSubscription->quantity = 5;

        $mockAction = Mockery::mock(SwapSubscriptionPlan::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(
                'subscription_test123',
                'plan_enterprise',
                Mockery::on(function ($options) {
                    // Should have custom option AND forced immediate flags
                    return $options['prorate'] === true
                        && $options['applyImmediately'] === true
                        && $options['invoiceImmediately'] === true;
                })
            )
            ->andReturn($apiSubscription);

        app()->instance(SwapSubscriptionPlan::class, $mockAction);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->vatly_id = 'subscription_test123';
        $subscription->shouldReceive('update')->once();

        $subscription->swapAndInvoice('enterprise', 'plan_enterprise', ['prorate' => true]);
    });

    test('it overrides user-provided immediate flags', function () {
        $apiSubscription = Mockery::mock(ApiSubscription::class);
        $apiSubscription->id = 'subscription_test123';
        $apiSubscription->subscriptionPlanId = 'plan_basic';
        $apiSubscription->quantity = 1;

        $mockAction = Mockery::mock(SwapSubscriptionPlan::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(
                'subscription_test123',
                'plan_basic',
                Mockery::on(function ($options) {
                    // Even if user passes false, we force true
                    return $options['applyImmediately'] === true
                        && $options['invoiceImmediately'] === true;
                })
            )
            ->andReturn($apiSubscription);

        app()->instance(SwapSubscriptionPlan::class, $mockAction);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->vatly_id = 'subscription_test123';
        $subscription->shouldReceive('update')->once();

        // User tries to pass false, but we override
        $subscription->swapAndInvoice('basic', 'plan_basic', [
            'applyImmediately' => false,
            'invoiceImmediately' => false,
        ]);
    });
});

afterEach(function () {
    Mockery::close();
});
