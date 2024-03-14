<?php

use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Onlime\LaravelHttpClientGlobalLogger\Mixins\PendingRequestMixin;

it('rebuilds the guzzle handler stack to include the logger middleware at the bottom of the stack', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    /** @var PendingRequest $pendingRequest */
    $pendingRequest = Http::withHeaders(['X-Test' => 'true'])->log();

    /** @var HandlerStack $handler */
    $handler = $pendingRequest->getOptions()['handler'];
    expect($handler)->toBeInstanceOf(HandlerStack::class);

    // We need to invade the HandlerStack to access the stack property
    $stack = invade($handler)->stack;
    // The first key is the middlware, and the second key is the name of the middleware
    $middlewareNames = Arr::pluck($stack, 1);

    expect($middlewareNames)->toContain('http-client-global-logger-0')
        ->and($middlewareNames)->toContain('http-client-global-logger-1')
        ->and($middlewareNames)->not->toContain('http-client-global-logger-2');
});
