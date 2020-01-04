<?php

namespace App\OAuth;

use Dingo\Api\Auth\Auth;
use Illuminate\Auth\CreatesUserProviders;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class OAuthServiceProvider extends ServiceProvider
{

    use CreatesUserProviders;

    public function register()
    {
    }

    public function boot()
    {
        Passport::routes();

        Passport::tokensCan([
            'read_user_data' => 'Read user data',
            'write_user_data' => 'Write user data',
        ]);

        $oauthProvider = $this->app->make(OAuth::class);
        $oauthProvider->setUserProvider($this->createUserProvider(config('auth.guards.api.provider')));
        $this->app[Auth::class]->extend('oauth', $oauthProvider);
    }
}
