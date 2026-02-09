<?php

declare(strict_types=1);

use Vatly\Actions\GetPaymentMethodUpdateUrl;
use Vatly\Actions\Responses\GetPaymentMethodUpdateUrlResponse;
use Vatly\Laravel\Models\Subscription;

describe('updatePaymentMethodUrl', function () {
    test('it returns the payment method update URL', function () {
        $expectedUrl = 'https://checkout.vatly.com/update-payment/abc123';

        $mockAction = Mockery::mock(GetPaymentMethodUpdateUrl::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with('subscription_test123', [])
            ->andReturn(new GetPaymentMethodUpdateUrlResponse(
                url: $expectedUrl,
                type: 'text/html',
            ));

        app()->instance(GetPaymentMethodUpdateUrl::class, $mockAction);

        $subscription = new Subscription(['vatly_id' => 'subscription_test123']);
        $url = $subscription->updatePaymentMethodUrl();

        expect($url)->toBe($expectedUrl);
    });

    test('it passes prefill data to the action', function () {
        $prefillData = ['billingAddress' => ['city' => 'Amsterdam']];

        $mockAction = Mockery::mock(GetPaymentMethodUpdateUrl::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with('subscription_test123', $prefillData)
            ->andReturn(new GetPaymentMethodUpdateUrlResponse(
                url: 'https://checkout.vatly.com/update',
                type: 'text/html',
            ));

        app()->instance(GetPaymentMethodUpdateUrl::class, $mockAction);

        $subscription = new Subscription(['vatly_id' => 'subscription_test123']);
        $url = $subscription->updatePaymentMethodUrl($prefillData);

        expect($url)->toBe('https://checkout.vatly.com/update');
    });
});

afterEach(function () {
    Mockery::close();
});
