<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Every URL the app fetches comes from a user, a feed or another server, so the
        // HTTP client only reaches public addresses unless a host is explicitly allowed.
        // Resolve the client with app(\p3k\HTTP::class) rather than `new`, to get this.
        $this->app->bind(\p3k\HTTP\Guard::class, function() {
            return new \p3k\HTTP\Guard(config('aperture.http.allow_private'));
        });

        $this->app->bind(\p3k\HTTP::class, function() {
            $http = new \p3k\HTTP();
            $http->set_safe_mode(true, config('aperture.http.allow_private'));
            return $http;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The IndieAuth client makes its own requests to URLs that people type in at
        // login and to the endpoints those pages advertise. Give it the guarded client.
        \IndieAuth\Client::$http = $this->app->make(\p3k\HTTP::class);
        \IndieAuth\Client::$http->set_user_agent('indieauth-client/'.\IndieAuth\Client::VERSION);
        \IndieAuth\Client::$http->set_timeout(10);

        Gate::define('edit-channel', function($user, $channel) {
            return $user->id == $channel->user_id;
        });
    }
}
