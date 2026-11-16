<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Mixins;

use Closure;
use GuzzleHttp\Middleware;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Onlime\LaravelHttpClientGlobalLogger\Support\ObfuscatingMessageFormatter;

/** @mixin PendingRequest */
class PendingRequestMixin
{
    public function log(): Closure
    {
        /**
         * @param  string|null  $name  logger name
         * @return $this
         */
        return function (?string $name = null) {
            if (! config('http-client-global-logger.enabled')) {
                return $this;
            }

            /** @var Logger $logger */
            $logger = Log::channel(config('http-client-global-logger.channel'));
            if (! is_null($name)) {
                $logger = $logger->withName($name);
            }

            // Both are logged once the response is received, the first middleware added last.
            // Reversed, so that the request is logged before the response.
            foreach (array_reverse(config('http-client-global-logger.format')) as $format) {
                $this->withMiddleware(Middleware::log($logger, new ObfuscatingMessageFormatter($format)));
            }

            return $this;
        };
    }
}
