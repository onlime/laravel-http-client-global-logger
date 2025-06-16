<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Psr\Http\Message\RequestInterface;
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
     * Handle the event.
     */
    public function handleEvent(RequestSending|SendingSaloonRequest $event): void
    {
        if (! EventHelper::shouldBeLogged($event)) {
            return;
        }

        $psrRequest = EventHelper::getPsrRequest($event);

        $obfuscate  = config('http-client-global-logger.obfuscate.enabled');

        if ($obfuscate) {
            $psrRequest = $this->obfuscateHeaders($psrRequest);
        }

        $formatter = new MessageFormatter(config('http-client-global-logger.format.request'));
        $message = $formatter->format($psrRequest);

        if ($obfuscate) {
            $replacement = config('http-client-global-logger.obfuscate.replacement');
            foreach (config('http-client-global-logger.obfuscate.body_keys') as $key) {
                $quoted = preg_quote($key, '/');
                // JSON-style: "key":"value"
                $message = preg_replace(
                    '/(?<="'.$quoted.'":")[^"]*(?=")/mU',
                    $replacement,
                    $message
                );
                // form-style: key=value (until & or end)
                $message = preg_replace(
                    '/(?<=\b'. $quoted .'=)[^&]*(?=&|$)/',
                    $replacement,
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
