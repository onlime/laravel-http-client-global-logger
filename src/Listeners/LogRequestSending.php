<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Onlime\LaravelHttpClientGlobalLogger\Support\UrlFilter;
use Onlime\LaravelHttpClientGlobalLogger\Traits\ObfuscatesBody;
use Onlime\LaravelHttpClientGlobalLogger\Traits\ObfuscatesHeaders;
use Saloon\Laravel\Events\SendingSaloonRequest;

class LogRequestSending
{
    use ObfuscatesBody;
    use ObfuscatesHeaders;

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
     * Handle the event.
     */
    public function handleEvent(RequestSending|SendingSaloonRequest $event): void
    {
        // In combined mode the request is logged together with the response by
        // LogResponseReceived, so there is no separate request entry to write here.
        if (config('http-client-global-logger.combined')) {
            return;
        }

        $psrRequest = EventHelper::getPsrRequest($event);

        if (! UrlFilter::shouldLog($psrRequest)) {
            return;
        }

        $obfuscate = config('http-client-global-logger.obfuscate.enabled');

        if ($obfuscate) {
            $psrRequest = $this->obfuscateHeaders($psrRequest);
        }

        $formatter = new MessageFormatter(config('http-client-global-logger.format.request'));
        $message = $formatter->format($psrRequest);

        if ($obfuscate) {
            $message = $this->obfuscateBody($message);
        }

        Log::channel(config('http-client-global-logger.channel'))
            ->info($message);
    }
}
