<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Mixins;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

/** @mixin PendingRequest */
class PendingRequestMixin
{
    public function log()
    {
        /**
         * @param  string|null  $name  logger name
         * @return $this
         */
        return function (?string $name = null) {
            if (! config('http-client-global-logger.enabled')) {
                return $this;
            }

            $messageFormats = array_values(
                config('http-client-global-logger.format')
            );

            $stack = $this->buildHandlerStack();

            /** @var Logger $logger */
            $logger = Log::channel(config('http-client-global-logger.channel'));
            if (! is_null($name)) {
                $logger = $logger->withName($name);
            }
            foreach ($messageFormats as $key => $format) {
                // We'll use unshift instead of push, to add the middleware to the bottom of the stack, not the top
                $stack->unshift(
                    Middleware::log($logger, new MessageFormatter($format)),
                    'http-client-global-logger-'.$key
                );
            }

            return $this->withOptions(['handler' => $stack]);
        };
    }
}
