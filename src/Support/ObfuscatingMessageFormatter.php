<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Support;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\MessageFormatterInterface;
use Illuminate\Support\Str;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Guzzle's MessageFormatter, with credentials obfuscated according to the
 * http-client-global-logger.obfuscate config: header values, the password
 * in the URL, and JSON-style and form/query-style values.
 */
class ObfuscatingMessageFormatter implements MessageFormatterInterface
{
    /**
     * Characters of a key that a '*' wildcard matches, e.g. 'user%5B' and '%5D'
     * in 'user%5Bpassword%5D' (user[password]) for the pattern '*password*'.
     */
    private const string WILDCARD = '[\w.%\[\]-]*';

    /**
     * A password hash in the modular crypt format, $<identifier>$…, e.g. $2y$ (bcrypt), $argon2id$, or legacy ones like
     * $1$ (MD5-crypt), $6$ (SHA-512-crypt) or $P$ (phpass), with at least 16 more characters up to the end of a JSON
     * string, form value or XML element, unlike short or spaced values like $5$ or $x$ + $y$. Its identifier is the
     * first group. Like in onlime/laravel-sql-reporter, only its identifier is kept, e.g. $2y$[redacted].
     */
    private const string HASH = '(\$[\w-]+\$)[^\s"\'&<]{16,}';

    private string $replacement;

    public function __construct(private string $template) {}

    public function format(RequestInterface $request, ?ResponseInterface $response = null, ?Throwable $error = null): string
    {
        $formatter = new MessageFormatter($this->template);

        if (! config('http-client-global-logger.obfuscate.enabled')) {
            return $formatter->format($request, $response, $error);
        }

        $this->replacement = (string) config('http-client-global-logger.obfuscate.replacement');

        $message = $formatter->format(
            $this->obfuscateHeaders($request),
            $response ? $this->obfuscateHeaders($response) : null,
            $error
        );

        return $this->obfuscateMessage($this->obfuscateUrlPassword($request, $message));
    }

    /**
     * Obfuscate headers by name, e.g. Authorization or Set-Cookie.
     *
     * @template T of MessageInterface
     *
     * @param  T  $message
     * @return T
     */
    private function obfuscateHeaders(MessageInterface $message): MessageInterface
    {
        $patterns = $this->patterns('headers');

        foreach (array_keys($message->getHeaders()) as $name) {
            if (Str::is($patterns, (string) $name, ignoreCase: true)) {
                $message = $message->withHeader((string) $name, $this->replacement);
            }
        }

        return $message;
    }

    /**
     * Obfuscate the password in the URL, e.g. https://user:password@example.com
     */
    private function obfuscateUrlPassword(RequestInterface $request, string $message): string
    {
        $userInfo = $request->getUri()->getUserInfo();

        if (! str_contains($userInfo, ':')) {
            return $message;
        }

        return str_replace($userInfo.'@', Str::before($userInfo, ':').':'.$this->replacement.'@', $message);
    }

    /**
     * Obfuscate the values of body keys in JSON-style and form/query-style text, and password hashes.
     *
     * This is mainly about the body, but it runs on the whole formatted message, as it needs the URL and the header
     * data as well: the query string of the URL, and values in headers not obfuscated by their name, e.g. a token in
     * "X-Debug: token=…". Header values already replaced by obfuscateHeaders() stay as they are, as a replacement
     * like [redacted] contains no key and value.
     */
    private function obfuscateMessage(string $message): string
    {
        foreach ($this->patterns('body_keys') as $pattern) {
            $key = str_replace('\*', self::WILDCARD, preg_quote($pattern, '/'));

            // 1. JSON-style: "key":"value"
            // The value is matched up to its closing quote, skipping escaped characters like \" or \\.
            // A value without closing quote (cut off by trimming the response body) is matched up to the end.
            // The possessive *+ (no backtracking) keeps values with many escaped characters within PCRE's limits.
            $message = $this->replaceValues('/("'.$key.'"\s*:\s*")(\\\\.|[^"\\\\]+)*+/i', $message);

            // 2. Form/query-style: key=value (stopping at & or whitespace)
            // A leading wildcard is left out, so that the match may start anywhere in a key, e.g. at 'Signature'
            // in 'X-Amz-Signature='. Matching it would get slow on long values.
            $formKey = str_starts_with($pattern, '*') ? Str::after($key, self::WILDCARD) : '\b'.$key;
            $message = $this->replaceValues('/('.$formKey.'=)[^&\s]*/i', $message);
        }

        // 3. Password hashes, also in the values of other keys: $2y$10$… => $2y$[redacted]
        return $this->replaceValues('/'.self::HASH.'/', $message);
    }

    /**
     * Replace the value following the first group of each match by the replacement, which is inserted literally.
     * A password hash keeps its algorithm identifier, e.g. "password":"$2y$[redacted]".
     * On a PCRE failure (null), never fall back to the unobfuscated message.
     */
    private function replaceValues(string $regex, string $message): string
    {
        return preg_replace_callback($regex, function (array $matches) {
            $value = substr($matches[0], strlen($matches[1]));

            return $matches[1].Str::match('/^'.self::HASH.'/', $value).$this->replacement;
        }, $message)
            ?? throw new RuntimeException('Body obfuscation failed: '.preg_last_error_msg());
    }

    /**
     * @return list<string>
     */
    private function patterns(string $option): array
    {
        $patterns = array_map(
            fn (mixed $pattern) => trim((string) $pattern),
            (array) config("http-client-global-logger.obfuscate.$option", [])
        );

        return array_values(array_filter($patterns));
    }
}
