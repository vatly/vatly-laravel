<?php

declare(strict_types=1);

namespace Vatly\Laravel;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Vatly\Actions\CancelSubscription;
use Vatly\Actions\CreateCheckout;
use Vatly\Actions\CreateCustomer;
use Vatly\Actions\GetCheckout;
use Vatly\Actions\GetCustomer;
use Vatly\Actions\GetPaymentMethodUpdateUrl;
use Vatly\Actions\GetSubscription;
use Vatly\Actions\SwapSubscriptionPlan;
use Vatly\API\VatlyApiClient;
use Vatly\Contracts\ConfigurationInterface;
use Vatly\Contracts\CustomerRepositoryInterface;
use Vatly\Contracts\EventDispatcherInterface;
use Vatly\Contracts\SubscriptionRepositoryInterface;
use Vatly\Contracts\WebhookCallRepositoryInterface;
use Vatly\Events\SubscriptionCanceledImmediately;
use Vatly\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Events\SubscriptionStarted;
use Vatly\Events\WebhookReceived;
use Vatly\Laravel\Events\LaravelEventDispatcher;
use Vatly\Laravel\Listeners\CancelSubscriptionImmediatelyListener;
use Vatly\Laravel\Listeners\CancelSubscriptionWithGracePeriodListener;
use Vatly\Laravel\Listeners\CascadeVatlyWebhookEvents;
use Vatly\Laravel\Listeners\StartSubscriptionListener;
use Vatly\Laravel\Repositories\EloquentCustomerRepository;
use Vatly\Laravel\Repositories\EloquentSubscriptionRepository;
use Vatly\Laravel\Repositories\EloquentWebhookCallRepository;
use Vatly\Webhooks\SignatureVerifier;
use Vatly\Webhooks\WebhookEventFactory;

class VatlyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/vatly.php', 'vatly'
        );

        $this->registerConfiguration();
        $this->registerApiClient();
        $this->registerActions();
        $this->registerRepositories();
        $this->registerWebhookUtilities();
        $this->registerEventDispatcher();
    }

    public function boot(): void
    {
        $this->bootRoutes();
        $this->bootEventListeners();
        $this->bootPublishing();
    }

    private function registerConfiguration(): void
    {
        $this->app->singleton(VatlyConfig::class);
        $this->app->bind(ConfigurationInterface::class, VatlyConfig::class);
    }

    private function registerApiClient(): void
    {
        $this->app->singleton(VatlyApiClient::class, function () {
            $config = $this->app->make(ConfigurationInterface::class);

            $client = new VatlyApiClient();
            $client->setApiKey($config->getApiKey());
            $client->setApiEndpoint($config->getApiUrl());
            $client->setApiVersion($config->getApiVersion());

            return $client;
        });
    }

    private function registerActions(): void
    {
        // All actions are registered as singletons that receive the API client
        $actions = [
            CreateCustomer::class,
            GetCustomer::class,
            CreateCheckout::class,
            GetCheckout::class,
            GetSubscription::class,
            GetPaymentMethodUpdateUrl::class,
            CancelSubscription::class,
            SwapSubscriptionPlan::class,
        ];

        foreach ($actions as $action) {
            $this->app->singleton($action, function () use ($action) {
                return new $action($this->app->make(VatlyApiClient::class));
            });
        }
    }

    private function registerRepositories(): void
    {
        $this->app->bind(SubscriptionRepositoryInterface::class, EloquentSubscriptionRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, EloquentCustomerRepository::class);
        $this->app->bind(WebhookCallRepositoryInterface::class, EloquentWebhookCallRepository::class);
    }

    private function registerWebhookUtilities(): void
    {
        $this->app->singleton(SignatureVerifier::class);
        $this->app->singleton(WebhookEventFactory::class);
    }

    private function registerEventDispatcher(): void
    {
        $this->app->bind(EventDispatcherInterface::class, LaravelEventDispatcher::class);
    }

    private function bootRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    private function bootEventListeners(): void
    {
        // Core webhook events mapped to Laravel listeners
        Event::listen(WebhookReceived::class, CascadeVatlyWebhookEvents::class);
        Event::listen(SubscriptionStarted::class, StartSubscriptionListener::class);
        Event::listen(SubscriptionCanceledImmediately::class, CancelSubscriptionImmediatelyListener::class);
        Event::listen(SubscriptionCanceledWithGracePeriod::class, CancelSubscriptionWithGracePeriodListener::class);
    }

    private function bootPublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/vatly.php' => config_path('vatly.php'),
        ], 'vatly-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_vatly_billable_columns.php.stub' => $this->getMigrationFileName('create_vatly_billable_columns.php'),
        ], 'vatly-billable-migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_vatly_subscriptions_table.php.stub' => $this->getMigrationFileName('create_vatly_subscriptions_table.php'),
            __DIR__.'/../database/migrations/create_vatly_webhook_calls_table.php.stub' => $this->getMigrationFileName('create_vatly_webhook_calls_table.php'),
        ], 'vatly-migrations');
    }

    private function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        return $this->app->databasePath("migrations/{$timestamp}_{$migrationFileName}");
    }
}
