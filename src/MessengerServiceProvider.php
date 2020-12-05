<?php

namespace RTippin\Messenger;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RTippin\Messenger\Brokers\FriendBroker;
use RTippin\Messenger\Commands\CallsActivityCheck;
use RTippin\Messenger\Commands\InvitesCheck;
use RTippin\Messenger\Commands\ProvidersCache;
use RTippin\Messenger\Commands\ProvidersClear;
use RTippin\Messenger\Commands\PurgeDocuments;
use RTippin\Messenger\Commands\PurgeImages;
use RTippin\Messenger\Commands\PurgeMessages;
use RTippin\Messenger\Commands\PurgeThreads;
use RTippin\Messenger\Contracts\BroadcastDriver;
use RTippin\Messenger\Contracts\FriendDriver;
use RTippin\Messenger\Contracts\PushNotificationDriver;
use RTippin\Messenger\Contracts\VideoDriver;
use RTippin\Messenger\Http\Middleware\AuthenticateOptional;
use RTippin\Messenger\Http\Middleware\MessengerApi;
use RTippin\Messenger\Http\Middleware\SetMessengerProvider;
use Illuminate\Contracts\Container\BindingResolutionException;

class MessengerServiceProvider extends ServiceProvider
{
    use ChannelMap;

    use PolicyMap;

    use RouteMap;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['messenger'];
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->registerHelpers();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerPolicies();
        $this->registerChannels();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'messenger');

        if ($this->app->runningInConsole())
        {
            $this->bootForConsole();
        }
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        $this->commands([
            CallsActivityCheck::class,
            InvitesCheck::class,
            ProvidersCache::class,
            ProvidersClear::class,
            PurgeDocuments::class,
            PurgeImages::class,
            PurgeMessages::class,
            PurgeThreads::class
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/messenger.php' => config_path('messenger.php'),
        ], 'messenger.config');

        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/messenger'),
        ], 'messenger.views');

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/messenger'),
        ], 'messenger.assets');

    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/messenger.php', 'messenger');

        $this->app->singleton(
            Messenger::class,
            Messenger::class
        );
        $this->app->alias(
            Messenger::class,
            'messenger'
        );
        $this->app->singleton(
            FriendDriver::class,
            FriendBroker::class
        );
        $this->app->singleton(
            BroadcastDriver::class,
            $this->getBroadcastImplementation()
        );
        $this->app->singleton(
            PushNotificationDriver::class,
            $this->getPushNotificationImplementation()
        );
        $this->app->singleton(
            VideoDriver::class,
            $this->getVideoImplementation()
        );
        $this->app->register(MessengerEventServiceProvider::class);
    }

    /**
     * @throws BindingResolutionException
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $kernel = $this->app->make(Kernel::class);

        $kernel->prependToMiddlewarePriority(MessengerApi::class);

        $router->aliasMiddleware(
            'messenger.api',
            MessengerApi::class
        );

        $router->aliasMiddleware(
            'messenger.provider',
            SetMessengerProvider::class
        );

        $router->aliasMiddleware(
            'auth.optional',
            AuthenticateOptional::class
        );
    }

    /**
     * Register helpers file
     * @noinspection PhpIncludeInspection
     */
    protected function registerHelpers(): void
    {
        if (file_exists($file = __DIR__.'/helpers.php'))
        {
            require_once $file;
        }
    }

    /**
     * Get the driver set in config for our services broadcasting feature
     *
     * @return string
     */
    protected function getBroadcastImplementation(): string
    {
        $alias = $this->app['config']->get('messenger.broadcasting.driver');

        return $this->app['config']->get('messenger.drivers.broadcasting')[$alias ?? 'null'];
    }

    /**
     * Get the driver set in config for our services push notifications
     *
     * @return string
     */
    protected function getPushNotificationImplementation(): string
    {
        $alias = $this->app['config']->get('messenger.push_notifications.driver');

        return $this->app['config']->get('messenger.drivers.push_notifications')[$alias ?? 'null'];
    }

    /**
     * Get the driver set in config for our services video feature
     *
     * @return string
     */
    protected function getVideoImplementation(): string
    {
        $alias = $this->app['config']->get('messenger.calling.driver');

        return $this->app['config']->get('messenger.drivers.calling')[$alias ?? 'null'];
    }
}
