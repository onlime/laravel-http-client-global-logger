<?php

namespace Onlime\LaravelHttpClientGlobalLogger;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogRequestSending;
use Psr\Http\Message\RequestInterface;

/**
 * Use HttpClientLogger::addRequestMiddleware() to manually add the middleware
 * after your own middleware in AppServiceProvider::boot() or similar.
 */
class HttpClientLogger
{
    private static bool $addedRequestMiddleware = false;

    public static function addRequestMiddleware(): void
    {
        Http::globalRequestMiddleware(
            fn (RequestInterface $psrRequest) => tap($psrRequest, function (RequestInterface $psrRequest) {
                // Wrap PSR-7 request into Laravel's HTTP Client Request object
                $clientRequest = new Request($psrRequest);

                // Instantiate event and listener
                $event = new RequestSending($clientRequest);
                $listener = new LogRequestSending;

                // Handle event
                $listener->handleEvent($event);
            })
        );

        self::$addedRequestMiddleware = true;
    }

    public static function requestMiddlewareWasAdded(): bool
    {
        return self::$addedRequestMiddleware;
    }
}
