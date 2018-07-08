<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        Route::pattern('user', '[0-9]+');
        Route::pattern('channel', '[0-9]+');
        Route::pattern('source_id', '[0-9]+');
        Route::pattern('entry', '[0-9a-zA-Z@\#]+');
        Route::pattern('channel_uid', '[0-9a-zA-Z]+');

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapWebSubRoutes();

        $this->mapMicrosubRoutes();

        $this->mapMicropubRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }

    protected function mapWebSubRoutes()
    {
        Route::middleware('websub')
          ->namespace($this->namespace)
          ->group(base_path('routes/websub.php'));
    }

    protected function mapMicrosubRoutes()
    {
        Route::middleware('microsub')
          ->namespace($this->namespace)
          ->group(base_path('routes/microsub.php'));
    }

    protected function mapMicropubRoutes()
    {
        Route::middleware('micropub')
          ->namespace($this->namespace)
          ->group(base_path('routes/micropub.php'));
    }
}
