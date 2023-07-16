<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;

class LogRequestSending
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(RequestSending $event)
    {
        $obfuscate  = config('http-client-global-logger.obfuscate.enabled');
        $psrRequest = $event->request->toPsrRequest();

        if ($obfuscate) {
            $psrRequest = $this->obfuscateHeaders($psrRequest);
        }

        $formatter = new MessageFormatter(config('http-client-global-logger.format.request'));
        $message = $formatter->format($psrRequest);

        if ($obfuscate) {
            foreach (config('http-client-global-logger.obfuscate.body_keys') as $key) {
                $message = preg_replace(
                    '/(?<="'.$key.'":").*(?=")/mU',
                    config('http-client-global-logger.obfuscate.replacement'),
                    $message
                );
            }
        }

        Log::channel(config('http-client-global-logger.channel'))->info($message);
    }

    /**
     * Obfuscate headers, e.g. Authorization header.
     */
    protected function obfuscateHeaders(RequestInterface $request): RequestInterface
    {
        $replacement = config('http-client-global-logger.obfuscate.replacement');

        // TODO: Currently, there is no clean way of modifying the PendingRequest body, e.g. via Macros
        // see https://stackoverflow.com/q/60603066/5982842
        // Tried to modify data directly on HTTP Client Request object, but PsrRequest is already set
        // $data = $request->data();
        // data_set($data, 'params.pass', $replacement);
        // $request = $request->withData($data);

        foreach (config('http-client-global-logger.obfuscate.headers') as $name) {
            if ($request->hasHeader($name)) {
                $request = $request->withHeader($name, $replacement);
            }
        }

        return $request;
    }
}
