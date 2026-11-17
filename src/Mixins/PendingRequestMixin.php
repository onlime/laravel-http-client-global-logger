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

            $logMiddleware = fn (string $key) => Middleware::log(
                $logger,
                new ObfuscatingMessageFormatter(config('http-client-global-logger.format.'.$key))
            );

            // Like the global logging variants, the request is logged with the changes of global middleware and of
            // middleware added before (added last, innermost), and the response with the changes of all middleware
            // (added first, outermost). Both are logged once the response is received, the innermost first.
            // Like withMiddleware(), but named, so that calling log() again replaces them.
            // @phpstan-ignore property.notFound ($this is the PendingRequest, its middleware property is protected)
            $middleware = $this->middleware;
            $middleware->put('http-client-global-logger-request', $logMiddleware('request'));
            $middleware->prepend($logMiddleware('response'), 'http-client-global-logger-response');

            return $this;
        };
    }
}
