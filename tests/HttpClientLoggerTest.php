<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

function setupLogger(): MockInterface
{
    HttpClientLogger::addRequestMiddleware();

    $logger = Mockery::mock(LoggerInterface::class);

    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    return $logger;
}

it('can add a global request middleware to log the requests', function () {
    Http::globalRequestMiddleware(
        fn (RequestInterface $psrRequest) => $psrRequest->withHeader('X-Test', 'test')
    );

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)
            ->toContain('REQUEST: GET https://example.com')
            ->and($message)
            ->toContain('X-Test: test');

        return true;
    })->once()->andReturnSelf();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)
            ->toContain('RESPONSE: HTTP/1.1 200 OK');

        return true;
    })->once()->andReturnSelf();

    Http::fake()->get('https://example.com');
});

it('can trim the response body', function (array $config, string $contentType, bool $shouldTrim, bool $addCharsetToContentType) {
    config(['http-client-global-logger.trim_response_body' => $config]);

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('REQUEST: GET https://example.com');
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($shouldTrim) {
        expect($message)->toContain($shouldTrim ? 'verylongbo...' : 'verylongbody');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('verylongbody', 200, [
            'Content-Type' => $contentType.($addCharsetToContentType ? '; charset=UTF-8' : ''),
        ]),
    ])->get('https://example.com');
})->with(
    [
        'disabled' =>  [
            'config' => [
                'enabled' => false,
                'limit' => 10,
                'content_type_whitelist' => ['application/json'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => false,
        ],
        'below_limit' => [
            'config' => [
                'enabled' => true,
                'limit' => 20,
                'content_type_whitelist' => ['application/json'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => false,
        ],
        'content_type_whitelisted' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['application/octet-stream'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => false,
        ],
        'trim' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['application/json'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => true,
        ],
        'no_content_type_trim' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['application/octet-stream'],
            ],
            'contentType' => '',
            'shouldTrim' => true,
        ],
        'no_content_type_whitelisted' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['', 'application/octet-stream'],
            ],
            'contentType' => '',
            'shouldTrim' => false,
        ],
    ],
    [
        'with-charset' => ['addCharsetToContentType' => true],
        'without-charset' => ['addCharsetToContentType' => false],
    ]
);

it('always trims the response body when special header is set on request', function (bool $withHeader) {
    config(['http-client-global-logger.trim_response_body' => [
        'enabled' => true,
        'limit' => 10,
        'content_type_whitelist' => ['application/json'],
    ]]);

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('REQUEST: GET https://example.com');
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($withHeader) {
        expect($message)->toContain($withHeader ? 'verylongbo...' : 'verylongbody');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('verylongbody', 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->withHeader($withHeader ? 'X-Global-Logger-Trim-Always' : 'X-Dummy', 'true')
        ->get('https://example.com');
})->with([true, false]);

it('obfuscates request header and body', function (string $body, string $expected) {
    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain('REQUEST: POST https://example.com')
            ->and($message)->toContain('Authorization: **********')
            ->and($message)->toContain($expected);
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('RESPONSE: HTTP/1.1 200 OK');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->withHeader('Authorization', 'Bearer 123')
        ->withBody($body)
        ->post('https://example.com');
})->with([
    'json-style' => [
        '{"key":"value","apikey":"s3cr3tK3y","token":"s0meT0k3n"}',
        '{"key":"value","apikey":"**********","token":"**********"}',
    ],
    // OpenID Connect example for POST /token endpoint
    // see https://openid.net/specs/openid-connect-core-1_0.html#RefreshingAccessToken
    'form-style-openid' => [
        'grant_type=refresh_token&refresh_token=r3fr3shT0k3n&client_id=1234&client_secret=53cr3t',
        'grant_type=refresh_token&refresh_token=**********&client_id=1234&client_secret=**********',
    ],
]);

it('obfuscates request uri via body obfuscation', function (string $uri, string $expected) {
    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain('REQUEST: GET '.$expected);
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('RESPONSE: HTTP/1.1 200 OK');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->get($uri);
})->with([
    'no-query-params' => [
        'https://example.com',
        'https://example.com',
    ],
    'sensitive-query-param' => [
        'https://example.com?access_key=1234',
        'https://example.com?access_key=**********',
    ],
    'other-query-param' => [
        'https://example.com?foo=bar&baz=qux',
        'https://example.com?foo=bar&baz=qux',
    ],
    'mixed-query-params' => [
        'https://example.com?token=xyz&foo=bar&access_key=1234',
        'https://example.com?token=**********&foo=bar&access_key=**********',
    ],
]);

it('obfuscates response body', function (string $body, string $expected) {
    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain('REQUEST: POST https://example.com')
            ->and($message)->toContain($expected);
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain('RESPONSE: HTTP/1.1 200 OK')
            ->and($message)->toContain($expected);
        return true;
    })->once();

    Http::fake([
        '*' => Http::response($body, 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->withBody($body)
        ->post('https://example.com');
})->with([
    'json-style-openid' => [
        '{"access_token":"s0meT0k3n-1","expires_in":300,"refresh_token":"s0meT0k3n-2","token_type":"Bearer","id_token":"s0meT0k3n-3","session_state":"1234-56","scope":"foo bar"}',
        '{"access_token":"**********","expires_in":300,"refresh_token":"**********","token_type":"Bearer","id_token":"**********","session_state":"1234-56","scope":"foo bar"}',
    ],
    'form-style' => [
        'grant_type=refresh_token&refresh_token=r3fr3shT0k3n&client_id=1234&client_secret=53cr3t',
        'grant_type=refresh_token&refresh_token=**********&client_id=1234&client_secret=**********',
    ],
]);
