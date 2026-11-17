<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Onlime\LaravelHttpClientGlobalLogger\Support\ObfuscatingMessageFormatter;

function formatRequest(Request $request): string
{
    return (new ObfuscatingMessageFormatter("{uri}\r\n{req_headers}\r\n{req_body}"))->format($request);
}

it('obfuscates request and response headers by case-insensitive wildcard patterns', function () {
    $request = new Request('GET', 'https://example.com', [
        'authorization' => 'Bearer s3cr3t-1',
        'Proxy-Authorization' => 'Basic s3cr3t-2',
        'Cookie' => 'session=s3cr3t-3',
        'PRIVATE-TOKEN' => 's3cr3t-4',
        'X-MiteApiKey' => 's3cr3t-5',
        'X-Api-Password' => 's3cr3t-7',
        'Accept' => 'application/json',
    ]);
    $response = new Response(200, ['Set-Cookie' => 'session=s3cr3t-6; HttpOnly', 'Content-Type' => 'application/json']);

    $message = (new ObfuscatingMessageFormatter("{req_headers}\r\n{res_headers}"))->format($request, $response);

    expect($message)->not->toContain('s3cr3t')
        ->and(substr_count($message, '[redacted]'))->toBe(7)
        ->and($message)->toContain('Accept: application/json', 'Content-Type: application/json');
});

it('obfuscates body keys in the values of other headers', function () {
    expect(formatRequest(new Request('GET', 'https://example.com', ['X-Debug' => 'token=s1 mode=full'])))
        ->toContain("\r\nX-Debug: token=[redacted] mode=full\r\n");
});

it('obfuscates body keys by case-insensitive wildcard patterns', function (string $body, string $expected) {
    expect(formatRequest(new Request('POST', 'https://example.com', [], $body)))->toEndWith("\r\n".$expected);
})->with([
    // The explicit body keys of v1.5.0 and earlier, all covered by the new default patterns
    'json, previous defaults' => [
        '{"pass":"s1","password":"s2","token":"s3","apikey":"s4","access_token":"s5","access_key":"s6","refresh_token":"s7","id_token":"s8","client_secret":"s9","name":"Alice"}',
        '{"pass":"[redacted]","password":"[redacted]","token":"[redacted]","apikey":"[redacted]","access_token":"[redacted]","access_key":"[redacted]","refresh_token":"[redacted]","id_token":"[redacted]","client_secret":"[redacted]","name":"Alice"}',
    ],
    'form, previous defaults' => [
        'pass=s1&password=s2&token=s3&apikey=s4&access_token=s5&access_key=s6&refresh_token=s7&id_token=s8&client_secret=s9&name=Alice',
        'pass=[redacted]&password=[redacted]&token=[redacted]&apikey=[redacted]&access_token=[redacted]&access_key=[redacted]&refresh_token=[redacted]&id_token=[redacted]&client_secret=[redacted]&name=Alice',
    ],
    'json' => [
        '{"Password":"s1","accessToken":"s2","apiKey":"s3","private_key":"s4","password_confirmation":"s5","clientSecret":"s6","name":"Alice"}',
        '{"Password":"[redacted]","accessToken":"[redacted]","apiKey":"[redacted]","private_key":"[redacted]","password_confirmation":"[redacted]","clientSecret":"[redacted]","name":"Alice"}',
    ],
    'json with spaces' => [
        '{ "password" : "s1", "name" : "Alice" }',
        '{ "password" : "[redacted]", "name" : "Alice" }',
    ],
    'json escaped quotes' => [
        '{"password":"s1\"s2\\\\","pass":"\\\\\"s3","name":"Alice \"A\""}',
        '{"password":"[redacted]","pass":"[redacted]","name":"Alice \"A\""}',
    ],
    // A leading wildcard matches anything, as the match may start anywhere in a key
    'json leading wildcard' => [
        '{"api key":"s1","user:password":"s2","name":"Alice"}',
        '{"api key":"[redacted]","user:password":"[redacted]","name":"Alice"}',
    ],
    'json-style backslash and line break' => [
        "{\"password\":\"s1\\\ns2\",\"name\":\"Alice\"}",
        '{"password":"[redacted]","name":"Alice"}',
    ],
    'json cut off by trimming' => [
        '{"name":"Alice","access_token":"s1s2s3...',
        '{"name":"Alice","access_token":"[redacted]',
    ],
    'json url with query string' => [
        '{"url":"https://example.com/?sig=s1&page=2"}',
        '{"url":"https://example.com/?sig=[redacted]&page=2"}',
    ],
    'form' => [
        'Password=s1&api_key=s2&X-Amz-Signature=s3&name=Alice',
        'Password=[redacted]&api_key=[redacted]&X-Amz-Signature=[redacted]&name=Alice',
    ],
    // A value is matched up to & or whitespace, including quotes, like in v1.5.0
    'quoted values' => [
        '<Login user="alice" password="s1"/> password=s2"s3&name=Alice',
        '<Login user="alice" password=[redacted] password=[redacted]&name=Alice',
    ],
    'nested form' => [
        'user%5Bname%5D=Alice&user%5Bpassword%5D=s1&data[api_token]=s2',
        'user%5Bname%5D=Alice&user%5Bpassword%5D=[redacted]&data[api_token]=[redacted]',
    ],
]);

it('keeps only the algorithm identifier of password hashes', function (string $body, string $expected) {
    expect(formatRequest(new Request('POST', 'https://example.com', [], $body)))->toEndWith("\r\n".$expected);
})->with([
    'json' => [
        '{"password":"$2y$12$R9h\/cIPz0gi.URNNX3kh2OPST9\/PgBkqquzi.Ss7KIUgO2t0jWMUW","hash":"$argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHQ$aGFzaGhhc2g","crypt":"$6$rounds=5000$saltsalt$c2FsdHNhbHRzYWx0","price":"$5$ only"}',
        '{"password":"$2y$[redacted]","hash":"$argon2id$[redacted]","crypt":"$6$[redacted]","price":"$5$ only"}',
    ],
    'form' => [
        'hash=$P$B12345678901234567890123456789&name=Alice&password=%242y%2412%24R9hcIPz0gi',
        'hash=$P$[redacted]&name=Alice&password=[redacted]',
    ],
    'xml' => [
        '<hash>$2y$12$R9h/cIPz0gi.URNNX3kh2OPST9/PgBkqquzi.Ss7KIUgO2t0jWMUW</hash><name>Alice</name>',
        '<hash>$2y$[redacted]</hash><name>Alice</name>',
    ],
    // Only the identifier of a known algorithm is kept, as the rest could be part of a password
    'unknown identifier' => [
        '{"password":"$Summer-2024$Abcdefghijklmnop1","name":"$MyName$0123456789abcdefgh"}&password=$MyPass$0123456789abcdefgh',
        '{"password":"[redacted]","name":"$MyName$0123456789abcdefgh"}&password=[redacted]',
    ],
    // Only the characters of a hash are redacted
    'text' => [
        'at Outer$Inner$1.run(Outer.java:42) with $1$saltsalt$abcdefghijklmnop (MD5-crypt)',
        'at Outer$Inner$1.run(Outer.java:42) with $1$[redacted] (MD5-crypt)',
    ],
]);

it('stays within PCRE limits on long values', function (string $body, string $expected) {
    $backtrackLimit = ini_set('pcre.backtrack_limit', '10000');

    try {
        expect(formatRequest(new Request('POST', 'https://example.com', [], $body)))->toEndWith($expected);
    } finally {
        ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
    }
})->with([
    // 200 KB of a value with many word boundaries, e.g. URL-encoded base64, or a long run of key characters
    'form' => ['data='.str_repeat('a.b-c%2B', 25_000).'&password=s1', '&password=[redacted]'],
    'json' => ['{"data":"'.str_repeat('a-b_c.d', 30_000).'","password":"s1"}', ',"password":"[redacted]"}'],
]);

it('leaves out a body it cannot obfuscate', function () {
    // A value with so many escaped characters that matching it exceeds the (lowered) backtrack limit
    $body = '{"password":"'.str_repeat('a\"', 50_000).'s1","name":"Alice"}';
    $backtrackLimit = ini_set('pcre.backtrack_limit', '1000');

    try {
        $message = formatRequest(new Request('POST', 'https://example.com/?token=s2', [], $body));
    } finally {
        ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
    }

    expect($message)->toStartWith("https://example.com/?token=[redacted]\r\n")
        ->and($message)->toEndWith("\r\n[body not logged: Body obfuscation failed: Backtrack limit exhausted]")
        ->and($message)->not->toContain('s1')
        ->and($message)->not->toContain('s2');
});

it('obfuscates query params in the uri', function () {
    expect(formatRequest(new Request('GET', 'https://example.com/?key=s1&page=2&X-Amz-Security-Token=s2')))
        ->toStartWith("https://example.com/?key=[redacted]&page=2&X-Amz-Security-Token=[redacted]\r\n");
});

it('obfuscates the password in the uri, keeping the host header', function () {
    expect(formatRequest(new Request('GET', 'https://user:s3cr3t@example.com/path', ['Host' => 'example.com'])))
        ->toStartWith("https://user:[redacted]@example.com/path\r\nGET /path HTTP/1.1\r\nHost: example.com\r\n");
});

it('ignores whitespace around configured patterns', function () {
    config([
        'http-client-global-logger.obfuscate.headers' => ['Authorization', ' Cookie '],
        'http-client-global-logger.obfuscate.body_keys' => ['password', ' token', ''],
    ]);

    expect(formatRequest(new Request('POST', 'https://example.com/?token=s1', ['Cookie' => 's2'], 'name=Alice')))
        ->toBe("https://example.com/?token=[redacted]\r\nPOST /?token=[redacted] HTTP/1.1\r\nHost: example.com\r\nCookie: [redacted]\r\nname=Alice");
});

it('inserts the replacement literally', function () {
    config(['http-client-global-logger.obfuscate.replacement' => '0$1\\1']);

    expect(formatRequest(new Request('POST', 'https://example.com', [], '{"password":"s1"}')))
        ->toEndWith("\r\n".'{"password":"0$1\\1"}');
});

it('does not obfuscate if disabled', function () {
    config(['http-client-global-logger.obfuscate.enabled' => false]);

    expect(formatRequest(new Request('POST', 'https://user:s1@example.com/?token=s2', ['Authorization' => 's3'], '{"password":"s4"}')))
        ->toBe("https://user:s1@example.com/?token=s2\r\nPOST /?token=s2 HTTP/1.1\r\nHost: example.com\r\nAuthorization: s3\r\n{\"password\":\"s4\"}");
});

it('formats with Guzzle\'s default template if none is given', function () {
    // E.g. with HTTP_CLIENT_GLOBAL_LOGGER_REQUEST_FORMAT=null, like in v1.5.0
    expect((new ObfuscatingMessageFormatter(null))->format(new Request('GET', 'https://example.com/?token=s1')))
        ->toContain('"GET /?token=[redacted] HTTP/1.1"');
});
