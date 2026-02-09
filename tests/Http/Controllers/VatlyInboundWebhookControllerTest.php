<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Vatly\Contracts\EventDispatcherInterface;
use Vatly\Contracts\WebhookCallRepositoryInterface;
use Vatly\Laravel\Http\Controllers\VatlyInboundWebhookController;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Webhooks\WebhookEventFactory;

class VatlyInboundWebhookControllerTest extends BaseTestCase
{
    use RefreshDatabase;

    private VatlyInboundWebhookController $controller;

    private array $dispatchedEvents = [];

    private array $recordedCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatchedEvents = [];
        $this->recordedCalls = [];

        $this->controller = new VatlyInboundWebhookController(
            new WebhookEventFactory(),
            $this->createMockWebhookCallRepository(),
            $this->createMockDispatcher(),
        );
    }

    /** @test */
    public function it_accepts_a_post_request(): void
    {
        $response = $this->controller->__invoke(
            request: new Request(
                [
                    'eventName' => 'subscription.started',
                    'resourceId' => 'testResourceId',
                    'resourceName' => 'subscription',
                    'object' => [
                        'data' => [
                            'customerId' => 'customer_foo_bar',
                            'subscriptionPlanId' => 'plan_foo_bar',
                            'quantity' => 1,
                            'name' => 'Test Plan',
                        ],
                    ],
                    'raisedAt' => now()->toIso8601String(),
                    'testmode' => true,
                ],
            ),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(null, $response->content());
        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertCount(1, $this->recordedCalls);
    }

    /** @test */
    public function it_handles_unknown_webhook_events(): void
    {
        $response = $this->controller->__invoke(
            request: new Request(
                [
                    'eventName' => 'unknown.event.type',
                    'resourceId' => 'testResourceId',
                    'resourceName' => 'unknown',
                    'object' => ['foo' => 'bar'],
                    'raisedAt' => now()->toIso8601String(),
                    'testmode' => true,
                ],
            ),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(null, $response->content());
        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertCount(1, $this->recordedCalls);
    }

    /** @test */
    public function it_ignores_requests_without_resource_id(): void
    {
        $response = $this->controller->__invoke(
            request: new Request(
                [
                    'eventName' => 'subscription.started',
                    'resourceName' => 'subscription',
                    'object' => ['foo' => 'bar'],
                    'raisedAt' => now()->toIso8601String(),
                    'testmode' => true,
                ],
            ),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertCount(0, $this->dispatchedEvents);
        $this->assertCount(0, $this->recordedCalls);
    }

    private function createMockWebhookCallRepository(): WebhookCallRepositoryInterface
    {
        $testCase = $this;

        return new class($testCase) implements WebhookCallRepositoryInterface {
            public function __construct(private VatlyInboundWebhookControllerTest $testCase)
            {
            }

            public function record(
                string $eventName,
                string $resourceId,
                string $resourceName,
                array $payload,
                DateTimeImmutable $raisedAt,
                bool $testmode,
                ?string $vatlyCustomerId = null,
            ): void {
                $this->testCase->recordedCalls[] = [
                    'eventName' => $eventName,
                    'resourceId' => $resourceId,
                    'resourceName' => $resourceName,
                    'payload' => $payload,
                    'raisedAt' => $raisedAt,
                    'testmode' => $testmode,
                    'vatlyCustomerId' => $vatlyCustomerId,
                ];
            }
        };
    }

    private function createMockDispatcher(): EventDispatcherInterface
    {
        $testCase = $this;

        return new class($testCase) implements EventDispatcherInterface {
            public function __construct(private VatlyInboundWebhookControllerTest $testCase)
            {
            }

            public function dispatch(object $event): void
            {
                $this->testCase->dispatchedEvents[] = $event;
            }
        };
    }
}
