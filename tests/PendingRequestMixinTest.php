<?php

declare(strict_types=1);

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
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

it('logs only once when Http::log() is called again', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    Event::forget(RequestSending::class);
    Event::forget(ResponseReceived::class);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('log')->twice()->with('info', Mockery::type('string'));
    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    $client = Http::fake()->log();
    $client->log()->get('https://example.com');
});

it('logs with the given logger name with Http::log($name)', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    Event::forget(RequestSending::class);
    Event::forget(ResponseReceived::class);

    config(['logging.channels.http-client' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
    Log::forgetChannel('http-client');

    Http::fake()->log('my-api')->get('https://example.com');

    /** @var TestHandler $handler */
    $handler = Log::channel('http-client')->getLogger()->getHandlers()[0];

    expect(array_map(fn (LogRecord $record) => $record->channel, $handler->getRecords()))->toBe(['my-api', 'my-api']);
});

it('does not log with Http::log() if disabled', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    Event::forget(RequestSending::class);
    Event::forget(ResponseReceived::class);

    config(['http-client-global-logger.enabled' => false]);
    Log::shouldReceive('channel')->never();

    expect(Http::fake()->log()->get('https://example.com')->ok())->toBeTrue();
});
