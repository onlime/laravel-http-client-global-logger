<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

it('logs the request and response as a single combined entry by default', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    // A single log record containing BOTH the request and the response — no separate
    // REQUEST entry, so concurrent calls can never interleave their two halves.
    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)
            ->toContain('REQUEST: GET https://example.com')
            ->and($message)->toContain('RESPONSE: HTTP/1.1 200 OK');

        return true;
    })->once()->andReturnSelf();

    Http::fake()->get('https://example.com');
});

it('keeps the individual REQUEST/RESPONSE format inside the combined entry', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    $logger->shouldReceive('info')->withArgs(function ($message) {
        // The request block comes first, the response block second — each unchanged.
        expect(strpos($message, 'REQUEST: POST https://example.com'))->toBe(0)
            ->and($message)->toContain("Authorization: **********\r\n")
            ->and(strpos($message, 'RESPONSE: HTTP/1.1 200 OK'))
            ->toBeGreaterThan(strpos($message, 'REQUEST:'));

        return true;
    })->once()->andReturnSelf();

    Http::fake([
        '*' => Http::response('', 200, ['Content-Type' => 'application/json']),
    ])->withHeader('Authorization', 'Bearer 123')->post('https://example.com');
});

it('logs two separate entries when combined is disabled', function () {
    config(['http-client-global-logger.combined' => false]);

    $logger = Mockery::mock(LoggerInterface::class);
    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    $logger->shouldReceive('info')
        ->withArgs(fn ($message) => str_contains($message, 'REQUEST: GET https://example.com'))
        ->once()->andReturnSelf();
    $logger->shouldReceive('info')
        ->withArgs(fn ($message) => str_contains($message, 'RESPONSE: HTTP/1.1 200 OK'))
        ->once()->andReturnSelf();

    Http::fake()->get('https://example.com');
});
