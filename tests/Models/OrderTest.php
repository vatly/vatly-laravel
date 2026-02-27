<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Models;

use App\Models\User;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Laravel\Models\Order;

test('it implements OrderInterface', function () {
    $order = new Order();

    expect($order)->toBeInstanceOf(OrderInterface::class);
});

test('it can be created with attributes', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'order-test@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->getKey(),
        'vatly_id' => 'ord_test_123',
        'status' => 'paid',
        'total' => 9900,
        'currency' => 'EUR',
        'invoice_number' => 'INV-2024-001',
        'payment_method' => 'credit_card',
    ]);

    expect($order->getVatlyId())->toBe('ord_test_123')
        ->and($order->getStatus())->toBe('paid')
        ->and($order->getTotal())->toBe(9900)
        ->and($order->getCurrency())->toBe('EUR')
        ->and($order->getInvoiceNumber())->toBe('INV-2024-001')
        ->and($order->getPaymentMethod())->toBe('credit_card')
        ->and($order->isPaid())->toBeTrue();
});

test('it has a morphTo owner relationship', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'order-owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $order = Order::create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->getKey(),
        'vatly_id' => 'ord_owner_123',
        'status' => 'paid',
        'total' => 4900,
        'currency' => 'EUR',
    ]);

    expect($order->owner)->toBeInstanceOf(User::class)
        ->and($order->owner->id)->toBe($user->id);
});

test('user can access orders via relationship', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'order-rel@example.com',
        'password' => bcrypt('password'),
    ]);

    Order::create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->getKey(),
        'vatly_id' => 'ord_rel_1',
        'status' => 'paid',
        'total' => 9900,
        'currency' => 'EUR',
    ]);

    Order::create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->getKey(),
        'vatly_id' => 'ord_rel_2',
        'status' => 'paid',
        'total' => 4900,
        'currency' => 'USD',
    ]);

    expect($user->orders)->toHaveCount(2);
});

test('isPaid returns false for non-paid orders', function () {
    $order = new Order(['status' => 'pending']);

    expect($order->isPaid())->toBeFalse();
});
