<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Http;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Vatly\Laravel\Facades\Vatly;
use Vatly\Laravel\Tests\BaseTestCase;

class WebhookRouteRegistrationTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Vatly::$registersRoutes = true;
    }

    protected function ignoreVatlyRoutes(): void
    {
        Vatly::ignoreRoutes();
    }

    public function test_it_registers_the_webhook_route_by_default(): void
    {
        $this->assertTrue($this->webhookRouteIsRegistered());
        $this->post('webhooks/vatly')->assertStatus(503);
    }

    #[DefineEnvironment('ignoreVatlyRoutes')]
    public function test_ignore_routes_stops_the_webhook_route_from_being_registered(): void
    {
        $this->assertFalse($this->webhookRouteIsRegistered());
        $this->post('webhooks/vatly')->assertNotFound();
    }

    private function webhookRouteIsRegistered(): bool
    {
        return collect(Route::getRoutes()->getRoutes())
            ->contains(fn (RoutingRoute $route): bool => $route->uri() === 'webhooks/vatly');
    }
}
