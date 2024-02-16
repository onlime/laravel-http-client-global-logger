<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

it('can add a global request middleware to log the requests', function () {
    Http::globalRequestMiddleware(
        fn (RequestInterface $psrRequest) => $psrRequest->withHeader('X-Test', 'test')
    );

    HttpClientLogger::addRequestMiddleware();

    $logger = Mockery::mock(LoggerInterface::class);

    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

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

it('can trim the body response', function (array $config, bool $shouldTrim) {
    config(['http-client-global-logger.trim_response_body' => $config]);

    HttpClientLogger::addRequestMiddleware();

    $logger = Mockery::mock(LoggerInterface::class);

    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('REQUEST: GET https://example.com');
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($shouldTrim) {
        expect($message)->toContain($shouldTrim ? 'verylongbo...' : 'verylongbody');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('verylongbody', 200, ['content-type' => 'application/octet-stream']),
    ])->get('https://example.com');
})->with(
    [
        'disabled' =>  [
            'config' => [
                'enabled' => false,
                'treshold' => 10,
                'content_type_whitelist' => ['application/json'],
            ],
            'shouldTrim' => false,
        ],
        'below_treshold' => [
            'config' => [
                'enabled' => true,
                'treshold' => 20,
                'content_type_whitelist' => ['application/json'],
            ],
            'shouldTrim' => false,
        ],
        'content_type_whitelisted' => [
            'config' => [
                'enabled' => true,
                'treshold' => 10,
                'content_type_whitelist' => ['application/octet-stream'],
            ],
            'shouldTrim' => false,
        ],
        'trim' => [
            'config' => [
                'enabled' => true,
                'treshold' => 10,
                'content_type_whitelist' => ['application/json'],
            ],
            'shouldTrim' => true,
        ],
    ]
);
