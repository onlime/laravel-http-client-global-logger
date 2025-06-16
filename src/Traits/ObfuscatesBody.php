<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Traits;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

trait ObfuscatesBody
{
    protected function obfuscateBody(string $message): string
    {
        $replacement = config('http-client-global-logger.obfuscate.replacement');

        // Build regex pattern for keys to obfuscate
        $keysPattern = implode('|', array_map(
            fn (string $key) => preg_quote($key, '/'),
            config('http-client-global-logger.obfuscate.body_keys')
        ));

        // JSON-style: "(key1|key2)": "someValue"
        $message = preg_replace(
            '/(?<="(?:' . $keysPattern . ')":")[^"]*(?=")/mU',
            $replacement,
            $message
        );
        // form-style: key1=someValue& or key2=someValue$
        $message = preg_replace(
            '/(?<=\b(?:'. $keysPattern .')=)[^&]*(?=&|$)/',
            $replacement,
            $message
        );

        return $message;
    }
}
