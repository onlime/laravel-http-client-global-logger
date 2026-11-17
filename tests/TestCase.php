<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Tests;

use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Onlime\LaravelHttpClientGlobalLogger\Providers\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionProperty;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset what HttpClientLogger::addRequestMiddleware() remembers in a static property,
        // so that only the tests calling it skip the RequestSending listener (variant 1)
        (new ReflectionProperty(HttpClientLogger::class, 'addedRequestMiddleware'))->setValue(null, false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
