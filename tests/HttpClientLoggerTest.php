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
