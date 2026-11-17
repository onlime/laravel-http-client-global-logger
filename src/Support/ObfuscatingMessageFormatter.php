<?php

declare(strict_types=1);

namespace Onlime\LaravelHttpClientGlobalLogger\Support;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\MessageFormatterInterface;
use GuzzleHttp\Psr7\Utils;
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
     * Characters of a key that a '*' wildcard within or at the end of a body key pattern matches, e.g. '%5D'
     * in 'user%5Bpassword%5D' (user[password]) for the pattern '*password*'. Up to 100 of them, so that a long
     * run of them, e.g. URL-encoded JSON with many tokens, is not scanned to its end at each match attempt,
     * which gets slow without PCRE's JIT.
     */
    private const string WILDCARD = '[\w.%\[\]-]{0,100}';

    /**
     * A password hash in the modular crypt format, $<identifier>$…, of a known algorithm: $2y$ (bcrypt, also $2$, $2a$,
     * $2b$, $2x$), $argon2id$ (also $argon2i$, $argon2d$), $y$ and $gy$ (yescrypt), $7$ (scrypt), or legacy ones like
     * $1$ (MD5-crypt), $5$ and $6$ (SHA-256/512-crypt), $P$ and $H$ (phpass), $sha1$ or $md5$. It is followed by at
     * least 16 characters of a hash, also a JSON-escaped slash (\/), unlike short or spaced values like $5$ or $x$ + $y$.
     * Its identifier is the first group, the only part kept, e.g. $2y$[redacted]. Only known ones are kept, as any other
     * part of a value like $Summer-2024$… could be part of a password.
     */
    private const string HASH = '(\$(?:2[abxy]?|argon2id|argon2i|argon2d|y|gy|7|1|5|6|P|H|sha1|md5)\$)[\w.\/\\\\+=,$-]{16,}';

    private string $replacement;

    /**
     * @param  string|null  $template  Guzzle's MessageFormatter template, its default (Apache's Common Log Format) if null
     */
    public function __construct(private ?string $template) {}

    public function format(RequestInterface $request, ?ResponseInterface $response = null, ?Throwable $error = null): string
    {
        if (! config('http-client-global-logger.obfuscate.enabled')) {
            return (new MessageFormatter($this->template))->format($request, $response, $error);
        }

        $this->replacement = (string) config('http-client-global-logger.obfuscate.replacement');

        try {
            return $this->formatObfuscated($request, $response, $error);
        } catch (RuntimeException $e) {
            // Never log a body that can't be obfuscated, e.g. a huge value exceeding PCRE's limits, but leave it out
            $body = Utils::streamFor('[body not logged: '.$e->getMessage().']');

            return $this->formatObfuscated($request->withBody($body), $response?->withBody($body), $error);
        }
    }

    private function formatObfuscated(RequestInterface $request, ?ResponseInterface $response, ?Throwable $error): string
    {
        $message = (new MessageFormatter($this->template))->format(
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
        $jsonKeys = $formKeys = [];

        foreach ($this->patterns('body_keys') as $pattern) {
            // A leading wildcard is left out, so that the match may start anywhere in a key, e.g. at 'Signature' in
            // 'X-Amz-Signature='. Matching it would get slow on long values. Without one, the key starts at a quote
            // (JSON-style) or at a word boundary (form/query-style).
            $leadingWildcard = str_starts_with($pattern, '*');
            $key = str_replace('\*', self::WILDCARD, preg_quote(ltrim($pattern, '*'), '/'));

            $jsonKeys[] = ($leadingWildcard ? '' : '"').$key;
            $formKeys[] = ($leadingWildcard ? '' : '\b').$key;
        }

        // All keys are matched by a single regex per style: it stays fast without PCRE's JIT, also with many matches,
        // and a value replaced for one key can't hide another one. Without any keys, it would match all values.
        if ($jsonKeys !== []) {
            // 1. JSON-style: "key":"value"
            // The value is matched up to its closing quote, skipping escaped characters like \" or \\.
            // A value without closing quote (cut off by trimming the response body) is matched up to the end.
            // The possessive *+ (no backtracking) keeps values with many escaped characters within PCRE's limits,
            // up to hundreds of thousands of them without PCRE's JIT. Beyond, format() leaves out the body.
            $jsonRegex = '/((?:'.implode('|', $jsonKeys).')"\s*:\s*")(\\\\.|[^"\\\\]+)*+/is';
            $message = $this->replaceValues($jsonRegex, $message);

            // 2. Form/query-style: key=value (stopping at & or whitespace)
            // After all JSON-style values, as a value may run past the end of a JSON string, into the next key.
            $formRegex = '/((?:'.implode('|', $formKeys).')=)[^&\s]*/i';
            $message = $this->replaceValues($formRegex, $message);
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
        $patterns = config("http-client-global-logger.obfuscate.$option");

        $patterns = array_map(
            fn (mixed $pattern) => trim((string) $pattern),
            // Also comma-separated like the env variable, e.g. in a published config file without explode()
            is_string($patterns) ? explode(',', $patterns) : (array) $patterns
        );

        return array_values(array_filter($patterns));
    }
}
