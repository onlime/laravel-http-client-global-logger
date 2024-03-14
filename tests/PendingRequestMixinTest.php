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

    // It adds a logger for each format (see config('http-client-global-logger.format'))
    expect($handler->hasHandler('http-client-global-logger-0'))->toBeTrue()
        ->and($handler->hasHandler('http-client-global-logger-1'))->toBeTrue();
});
