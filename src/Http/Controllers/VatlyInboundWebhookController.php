<?php

declare(strict_types=1);

namespace Vatly\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Vatly\API\Exceptions\ApiException;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Webhooks\SignatureVerifier;
use Vatly\Fluent\Webhooks\WebhookProcessor;

class VatlyInboundWebhookController
{
    public function __invoke(Request $request): Response
    {
        if (config('app.debug')) {
            // Ids only: the body carries the customer's personal data.
            Log::info('Vatly Webhook request received!', [
                'id' => $request->input('id'),
                'eventName' => $request->input('eventName'),
                'entityType' => $request->input('entityType'),
                'entityId' => $request->input('entityId'),
            ]);
        }

        // Resolved here rather than injected: building the Vatly client
        // rejects an empty or malformed VATLY_KEY, and that has to become a
        // clear 503 (Vatly retries once the key is set) instead of a 500.
        try {
            $processor = app(WebhookProcessor::class);
        } catch (ApiException) {
            // The client's message repeats the key, so it is not logged.
            if (trim((string) config('vatly.api_key')) === '') {
                Log::error('Vatly webhook refused: VATLY_KEY is not set. Set your Vatly API key to receive webhooks.');

                return response('api_key_not_configured', 503);
            }

            Log::error("Vatly webhook refused: VATLY_KEY is not a valid Vatly API key. It must start with 'test_' or 'live_'.");

            return response('api_key_invalid', 503);
        }

        try {
            $processor->handle(
                payload: (string) $request->getContent(),
                signature: (string) $request->header(SignatureVerifier::SIGNATURE_HEADER_NAME, ''),
            );
        } catch (InvalidWebhookSignatureException $exception) {
            // No secret configured: refuse without processing, and answer 503
            // so Vatly retries the delivery once VATLY_WEBHOOK_SECRET is set.
            if ($exception->isSecretNotConfigured()) {
                Log::error('Vatly webhook refused: VATLY_WEBHOOK_SECRET is not set. Copy the signing secret from your Vatly dashboard.');

                return response('webhook_secret_not_configured', 503);
            }

            return response('', 403);
        }

        return response('', 201);
    }
}
