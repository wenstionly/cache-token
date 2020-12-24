<?php


namespace Wenstionly\CacheToken;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Wenstionly\CacheToken\Facades\CacheToken;

class CacheTokenProvider extends ServiceProvider
{

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/cache_token.php' => base_path('config/cache_token.php'),
        ], 'cache-token');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/cache_token.php', 'cache_token'
        );

        Auth::extend('cache-token', function($app, $name, $config) {
            $guard = new CacheTokenDriver(
                $app['auth']->createUserProvider($config['provider'] ?? null),
                $app['request'],
                $config
            );
            $app->refresh('request', $guard, 'setRequest');
            return $guard;
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CacheToken::class, function() {
            return new CacheTokenManager([]);
        });
        $this->app->alias(CacheToken::class, 'CacheToken');//这里注册的别名用来在Facades\CacheToken中返回
    }

//    public function provides()
//    {
//        return ['CacheToken', CacheTokenManager::class];
//    }
}
