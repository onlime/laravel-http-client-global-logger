<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger;

use Closure;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogRequestSending;
use Onlime\LaravelHttpClientGlobalLogger\Support\UrlFilter;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Use HttpClientLogger::addRequestMiddleware() to manually add the middleware
 * after your own middleware in AppServiceProvider::boot() or similar.
 */
class HttpClientLogger
{
    private static bool $addedRequestMiddleware = false;

    private static bool $reportingFailure = false;

    public static function addRequestMiddleware(): void
    {
        if (! config('http-client-global-logger.enabled')) {
            return;
        }

        Http::globalRequestMiddleware(
            fn (RequestInterface $psrRequest) => tap($psrRequest, function (RequestInterface $psrRequest) {
                if (! UrlFilter::shouldLog($psrRequest)) {
                    return;
                }

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

    /**
     * Run a logging callback without ever breaking the HTTP request, like Laravel's rescue(): any failure is
     * reported instead. HTTP requests made while reporting it (e.g. by an error tracker) are not logged,
     * as their logging would fail and be reported again, endlessly.
     */
    public static function rescue(Closure $callback): void
    {
        if (self::$reportingFailure) {
            return;
        }

        rescue($callback, function (Throwable $e) {
            self::$reportingFailure = true;

            try {
                report($e);
            } finally {
                self::$reportingFailure = false;
            }
        }, report: false);
    }
}
