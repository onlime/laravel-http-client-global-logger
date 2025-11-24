<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Traits;

trait ObfuscatesBody
{
    protected function obfuscateBody(string $message): string
    {
        $replacement = config('http-client-global-logger.obfuscate.replacement');

        $bodyKeys = config('http-client-global-logger.obfuscate.body_keys');

        // For each key, replace JSON-style and query param style
        foreach ($bodyKeys as $key) {
            $quoted = preg_quote($key, '/');

            // NOTES:
            // No multiline (/m) or ungreedy (/U) flags are needed; you only want to match within each line.
            // Each match is replaced in-place without modifying the rest of the string.

            // 1. JSON-style: "key":"value"
            $message = preg_replace(
                '/("'.$quoted.'"\s*:\s*")[^"]*(")/',
                '$1'.$replacement.'$2',
                $message
            );

            // 2. Form/query-style: key=value (stopping at & or end)
            // Using preg_replace_callback, so we don’t accidentally re-use the matched value or cause duplicates.
            // The pattern (\bkey=)[^&\s]* will match only the value, and not cross line breaks or ampersands.
            $message = preg_replace_callback(
                '/(\b'.$quoted.'=)[^&\s]*/',
                fn ($matches) => $matches[1].$replacement,
                $message
            );
        }

        return $message;
    }
}
