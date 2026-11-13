<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Tests;

use Onlime\LaravelHttpClientGlobalLogger\Providers\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
