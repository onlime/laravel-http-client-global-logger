<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Tests;

use Onlime\LaravelHttpClientGlobalLogger\Providers\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }
}
