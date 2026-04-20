<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Support;

use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

class UrlFilter
{
    public static function shouldLog(RequestInterface $request): bool
    {
        $url = (string) $request->getUri();
        $patterns = config('http-client-global-logger.except', []);

        if (empty($patterns)) {
            return true;
        }

        return ! Str::is($patterns, $url);
    }
}
