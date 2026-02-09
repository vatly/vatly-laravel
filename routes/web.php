<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vatly\Laravel\Http\Controllers\VatlyInboundWebhookController;
use Vatly\Laravel\Http\Middleware\VerifyVatlyWebhookSignature;

Route::middleware(VerifyVatlyWebhookSignature::class)
    ->post('webhooks/vatly', VatlyInboundWebhookController::class)
    ->name('webhook');
