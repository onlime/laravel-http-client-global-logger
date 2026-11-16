<?php

declare(strict_types=1);

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Onlime\LaravelHttpClientGlobalLogger\Mixins\PendingRequestMixin;
use Psr\Log\LoggerInterface;

it('logs the obfuscated request and response with Http::log()', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    // With the mixin variant enabled, the global logging listeners are not registered
    Event::forget(RequestSending::class);
    Event::forget(ResponseReceived::class);

    $messages = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('log')->twice()->with('info', Mockery::type('string'))
        ->andReturnUsing(function (string $level, string $message) use (&$messages) {
            $messages[] = $message;
        });
    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    Http::fake(['*' => Http::response('{"access_token":"s3cr3t-1"}', 200, ['Set-Cookie' => 'session=s3cr3t-2'])])
        ->log()
        ->withToken('s3cr3t-3')
        ->post('https://example.com/?token=s3cr3t-4', ['password' => 's3cr3t-5']);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toStartWith('REQUEST: POST https://example.com/?token=[redacted]')
        ->and($messages[1])->toStartWith('RESPONSE: HTTP/1.1 200 OK')
        ->and(implode("\n", $messages))->not->toContain('s3cr3t');
});
