<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;
use Exception;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::extend('jwt-cached', function ($app, $name, array $config) {
            return new class($app['tymon.jwt'], Auth::createUserProvider($config['provider']), $app['request'])
            extends \Tymon\JWTAuth\JWTGuard {

                public function user()
                {
                    try {
                        $t1 = microtime(true);
                        $id = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload()->get('sub');
                        $t2 = microtime(true);
                        logger("parseToken: " . round(($t2 - $t1) * 1000) . "ms");

                        $user = Cache::remember("jwt_user:{$id}", 300, fn() => parent::user());
                        $t3 = microtime(true);
                        logger("cache get: " . round(($t3 - $t2) * 1000) . "ms");

                        return $user;
                    } catch (Exception $e) {
                        logger("JWT error: " . $e->getMessage());
                        return null;
                    }
                }
            };
        });
    }
}
