<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Saloon\Laravel\Events\SentSaloonRequest;

class LogResponseReceived
{
    /**
     * Handle the event.
     */
    public function handle(ResponseReceived|SentSaloonRequest $event): void
    {
        if (! EventHelper::shouldBeLogged($event)) {
            return;
        }

        $formatter = new MessageFormatter(config('http-client-global-logger.format.response'));
        Log::channel(config('http-client-global-logger.channel'))->info($formatter->format(
            EventHelper::getPsrRequest($event),
            EventHelper::getPsrResponse($event),
        ));
    }
}
