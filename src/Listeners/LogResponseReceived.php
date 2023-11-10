<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\SaloonHelper;
use Saloon\Laravel\Events\SentSaloonRequest;

class LogResponseReceived
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(ResponseReceived|SentSaloonRequest $event)
    {
        if (! SaloonHelper::shouldBeLogged($event)) {
            return;
        }

        $psrRequest = $event instanceof ResponseReceived
            ? $event->request->toPsrRequest()
            : $event->pendingRequest->createPsrRequest();

        $psrResponse = $event instanceof ResponseReceived
            ? $event->response->toPsrResponse()
            : $event->response->getPsrResponse();

        $formatter = new MessageFormatter(config('http-client-global-logger.format.response'));
        Log::channel(config('http-client-global-logger.channel'))->info($formatter->format(
            $psrRequest,
            $psrResponse
        ));
    }
}
