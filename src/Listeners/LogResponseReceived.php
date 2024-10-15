<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Psr\Http\Message\MessageInterface;
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
        $psrRequest = EventHelper::getPsrRequest($event);
        Log::channel(config('http-client-global-logger.channel'))->info($formatter->format(
            $psrRequest,
            $this->trimBody(
                EventHelper::getPsrResponse($event),
                $psrRequest->hasHeader('X-Global-Logger-Trim-Always')
            )
        ));
    }

    /**
     * Trim the response body when it's too long.
     */
    private function trimBody(Response $psrResponse, bool $trimAlways = false): Response|MessageInterface
    {
        // Check if trimming is enabled
        if (! config('http-client-global-logger.trim_response_body.enabled')) {
            return $psrResponse;
        }

        if (! $trimAlways) {
            // E.g.: application/json; charset=utf-8 => application/json
            $contentTypeHeader = Str::of($psrResponse->getHeaderLine('Content-Type'))
                ->before(';')
                ->trim()
                ->lower()
                ->value();

            $whiteListedContentTypes = array_map(
                fn (string $type) => trim(strtolower($type)),
                config('http-client-global-logger.trim_response_body.content_type_whitelist')
            );

            // Check if the content type is whitelisted
            if (in_array($contentTypeHeader, $whiteListedContentTypes)) {
                return $psrResponse;
            }
        }

        $limit = config('http-client-global-logger.trim_response_body.limit');

        // Check if the body size exceeds the limit
        return ($psrResponse->getBody()->getSize() <= $limit)
            ? $psrResponse
            : $psrResponse->withBody(Utils::streamFor(
                Str::limit($psrResponse->getBody(), $limit)
            ));
    }
}
