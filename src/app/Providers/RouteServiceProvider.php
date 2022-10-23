<?php

namespace App\Providers;

use App\Events\ConfigureRoutes;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     * In addition, it is set as the URL generator's root namespace.
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define the routes for the application.
     */
    public function map(Router $router)
    {
        $this->mapStaticRoutes($router);

        $this->mapWebRoutes($router);

        $this->mapApiRoutes();

        Passport::routes();
        foreach ($router->getRoutes()->getRoutesByName() as $name => $route) {
            if (Str::startsWith($name, ['passport.authorizations', 'passport.tokens', 'passport.clients'])) {
                $route->middleware('verified');
            }
        }

        event(new ConfigureRoutes($router));
    }

    /**
     * Define the "web" routes for the application.
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(Router $router)
    {
        Route::middleware(['web'])
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "static" routes for the application.
     * These routes will not load session, etc.
     */
    protected function mapStaticRoutes(Router $router)
    {
        Route::namespace($this->namespace)
            ->group(base_path('routes/static.php'));
    }

    /**
     * Define the "api" routes for the application.
     * These routes are typically stateless.
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware(
                config('app.env') == 'testing' ? ['api'] : ['api', 'throttle:60,1']
            )
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }
}
