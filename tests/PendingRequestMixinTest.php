<?php

use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Onlime\LaravelHttpClientGlobalLogger\Mixins\PendingRequestMixin;

it('rebuilds the guzzle handler stack to include the logger middleware at the bottom of the stack', function () {
    PendingRequest::mixin(new PendingRequestMixin);

    $pendingRequest = Http::withHeaders(['X-Test' => 'true'])->log();

    /** @var HandlerStack $handler */
    $handler = $pendingRequest->getOptions()['handler'];

    // We need to invade the HandlerStack to access the stack property or findByName method
    expect($handler)->toBeInstanceOf(HandlerStack::class)
        ->and(invade($handler)->findByName('http-client-global-logger-0'))->toBeInt()
        ->and(invade($handler)->findByName('http-client-global-logger-1'))->toBeInt()
        ->and(fn () => invade($handler)->findByName('http-client-global-logger-2'))
        ->toThrow(\InvalidArgumentException::class);
});
