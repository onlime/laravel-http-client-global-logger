<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Listeners;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Onlime\LaravelHttpClientGlobalLogger\Support\UrlFilter;
use Onlime\LaravelHttpClientGlobalLogger\Traits\ObfuscatesBody;
use Onlime\LaravelHttpClientGlobalLogger\Traits\ObfuscatesHeaders;
use Psr\Http\Message\MessageInterface;
use Saloon\Laravel\Events\SentSaloonRequest;

class LogResponseReceived
{
    use ObfuscatesBody;
    use ObfuscatesHeaders;

    /**
     * Handle the event.
     */
    public function handle(ResponseReceived|SentSaloonRequest $event): void
    {
        $psrRequest = EventHelper::getPsrRequest($event);

        if (! UrlFilter::shouldLog($psrRequest)) {
            return;
        }

        $obfuscate = config('http-client-global-logger.obfuscate.enabled');

        $message = (new MessageFormatter(config('http-client-global-logger.format.response')))->format(
            $psrRequest,
            $this->trimBody(
                EventHelper::getPsrResponse($event),
                $psrRequest->hasHeader('X-Global-Logger-Trim-Always')
            )
        );

        // Combined mode: prepend the request so the whole call is a single atomic entry
        // (no separate request entry is written by LogRequestSending in this mode).
        if (config('http-client-global-logger.combined')) {
            $request = $obfuscate ? $this->obfuscateHeaders($psrRequest) : $psrRequest;
            $requestMessage = (new MessageFormatter(config('http-client-global-logger.format.request')))->format($request);
            $message = $requestMessage."\n".$message;
        }

        if ($obfuscate) {
            $message = $this->obfuscateBody($message);
        }

        Log::channel(config('http-client-global-logger.channel'))
            ->info($message);
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
