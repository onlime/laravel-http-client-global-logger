<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class LogRequestSending
{
    /**
     * Handle the event.
     *
     * @param RequestSending $event
     * @return void
     */
    public function handle(RequestSending $event)
    {
        $formatter = new MessageFormatter(config('http-client-global-logger.format.request'));
        $message = $formatter->format(
            $event->request->toPsrRequest()
        );

        if (config('http-client-global-logger.obfuscate.enabled')) {
            $message = preg_replace(
                config('http-client-global-logger.obfuscate.patterns'),
                config('http-client-global-logger.obfuscate.replacement'),
                $message
            );
        }

        Log::channel(config('http-client-global-logger.channel'))->info($message);
    }
}
