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

it('can trim the body response', function (array $config, string $contentType, bool $shouldTrim, bool $addCharsetToContentType) {
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
    [true, false]
);
