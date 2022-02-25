<?php

namespace Ma\AuthOtpApi;

use Illuminate\Support\ServiceProvider;

class AuthApiServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->mergeConfigFrom(__DIR__ . '/config/auth-otp-api.php', 'auth-otp-api');
        $this->publishes([
            __DIR__ . '/config/auth-otp-api.php' => config_path('auth-otp-api.php'),
        ]);
    }
    public function register()
    {
    }
}
