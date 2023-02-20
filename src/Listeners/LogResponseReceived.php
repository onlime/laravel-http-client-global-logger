<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;

class LogResponseReceived
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(ResponseReceived $event)
    {
        $formatter = new MessageFormatter(config('http-client-global-logger.format.response'));
        Log::channel(config('http-client-global-logger.channel'))->info($formatter->format(
            $event->request->toPsrRequest(), $event->response->toPsrResponse()
        ));
    }
}
