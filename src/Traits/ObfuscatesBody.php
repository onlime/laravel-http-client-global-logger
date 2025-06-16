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

        return $message;
    }
}
