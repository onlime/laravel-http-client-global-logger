<?php

use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Onlime\LaravelHttpClientGlobalLogger\Mixins\PendingRequestMixin;

it('rebuilds the guzzle handler stack to include the logger middleware at the bottom of the stack', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    /** @var PendingRequest $pendingRequest */
    $pendingRequest = Http::withHeaders(['X-Test' => 'true'])->log();

    /** @var HandlerStack $handler */
    $handler = $pendingRequest->getOptions()['handler'];
    expect($handler)->toBeInstanceOf(HandlerStack::class);

    // String representation of the stack
    $stack = $handler->__toString();

    // It adds a logger for each format (see config('http-client-global-logger.format'))
    expect($stack)->toContain("Name: 'http-client-global-logger-0'")
        ->and($stack)->toContain("Name: 'http-client-global-logger-1'")
        ->and($stack)->not->toContain("Name: 'http-client-global-logger-2'");
});
