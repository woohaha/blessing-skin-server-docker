<?php

namespace Blessing;

use Illuminate\Support\ServiceProvider;

class FilterServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Filter::class);
    }

    public function boot()
    {
    }
}
