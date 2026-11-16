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

it('logs the request and response with Http::log()', function () {
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

    Http::fake(['*' => Http::response('{"name":"Alice"}', 200)])
        ->log()
        ->post('https://example.com', ['name' => 'Bob']);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toStartWith('REQUEST: POST https://example.com')
        ->and($messages[1])->toStartWith('RESPONSE: HTTP/1.1 200 OK');
});
