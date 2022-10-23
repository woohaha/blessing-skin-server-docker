<?php

namespace Tests;

use Blessing\Filter;
use Blessing\FilterServiceProvider;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    public function testRegister()
    {
        $container = new Container();
        $container->instance(
            \Illuminate\Contracts\Container\Container::class,
            $container
        );
        $provider = new FilterServiceProvider($container);

        $provider->register();
        $provider->boot();

        $instance = $container->make(Filter::class);
        $this->assertInstanceof(Filter::class, $instance);
        $this->assertSame($instance, $container->make(Filter::class));
    }
}
