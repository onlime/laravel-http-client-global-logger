<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Onlime\LaravelHttpClientGlobalLogger\Support\ObfuscatingMessageFormatter;
use Onlime\LaravelHttpClientGlobalLogger\Support\UrlFilter;
use Saloon\Laravel\Events\SendingSaloonRequest;

class LogRequestSending
{
    /**
     * Handle the event if the HTTP Client global request middleware was not added manually
     * with HttpClientLogger::addRequestMiddleware(). Always handle it for Saloon requests.
     */
    public function handle(RequestSending|SendingSaloonRequest $event): void
    {
        if ($event instanceof RequestSending && HttpClientLogger::requestMiddlewareWasAdded()) {
            return;
        }

        $this->handleEvent($event);
    }

    /**
     * Handle the event. Any failure is reported, never breaking the HTTP request.
     */
    public function handleEvent(RequestSending|SendingSaloonRequest $event): void
    {
        rescue(function () use ($event) {
            $psrRequest = EventHelper::getPsrRequest($event);

            if (! UrlFilter::shouldLog($psrRequest)) {
                return;
            }

            $formatter = new ObfuscatingMessageFormatter(config('http-client-global-logger.format.request'));

            Log::channel(config('http-client-global-logger.channel'))
                ->info($formatter->format($psrRequest));
        });
    }
}
