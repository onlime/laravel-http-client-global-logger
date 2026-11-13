<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Traits;

use RuntimeException;

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
            // On a PCRE failure (null), never fall back to the unobfuscated message.

            // 1. JSON-style: "key":"value"
            $message = preg_replace(
                '/("'.$quoted.'"\s*:\s*")[^"]*(")/',
                '$1'.$replacement.'$2',
                $message
            ) ?? throw new RuntimeException('Body obfuscation failed: '.preg_last_error_msg());

            // 2. Form/query-style: key=value (stopping at & or end)
            // Using preg_replace_callback, so we don’t accidentally re-use the matched value or cause duplicates.
            // The pattern (\bkey=)[^&\s]* will match only the value, and not cross line breaks or ampersands.
            $message = preg_replace_callback(
                '/(\b'.$quoted.'=)[^&\s]*/',
                fn (array $matches) => $matches[1].$replacement,
                $message
            ) ?? throw new RuntimeException('Body obfuscation failed: '.preg_last_error_msg());
        }

        return $message;
    }
}
