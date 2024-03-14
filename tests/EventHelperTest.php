<?php

use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Onlime\LaravelHttpClientGlobalLogger\EventHelper;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Events\SendingSaloonRequest;
use Saloon\Laravel\Events\SentSaloonRequest;

function psr7Request(): Psr7Request
{
    return new Psr7Request('GET', 'http://localhost/test');
}

function psr7Response(): Psr7Response
{
    return new Psr7Response(200, ['X-Foo' => 'Bar']);
}

function laravelHttpRequest(): ClientRequest
{
    return new ClientRequest(psr7Request());
}

function laravelHttpResponse(): ClientResponse
{
    return new ClientResponse(psr7Response());
}

it('resolves the psr request from Laravel\'s RequestSending event', function () {
    $psrRequest = EventHelper::getPsrRequest(new RequestSending(laravelHttpRequest()));

    expect($psrRequest)->toBeInstanceOf(Psr7Request::class)
        ->and($psrRequest->getMethod())->toBe('GET')
        ->and($psrRequest->getUri()->__toString())->toBe('http://localhost/test');
});

it('resolves the psr request from Laravel\'s ResponseReceived event', function () {
    $psrRequest = EventHelper::getPsrRequest(new ResponseReceived(laravelHttpRequest(), laravelHttpResponse()));

    expect($psrRequest)->toBeInstanceOf(Psr7Request::class)
        ->and($psrRequest->getMethod())->toBe('GET')
        ->and($psrRequest->getUri()->__toString())->toBe('http://localhost/test');
});

it('resolves the psr response from Laravel\'s ResponseReceived event', function () {
    $psrResponse = EventHelper::getPsrResponse(new ResponseReceived(laravelHttpRequest(), laravelHttpResponse()));

    expect($psrResponse)->toBeInstanceOf(Psr7Response::class)
        ->and($psrResponse->getStatusCode())->toBe(200)
        ->and($psrResponse->getHeaderLine('X-Foo'))->toBe('Bar');
});

function saloonPendingRequest(): PendingRequest
{
    return new PendingRequest(
        new class extends Connector
        {
            public function resolveBaseUrl(): string
            {
                return 'http://localhost';
            }
        },
        new class extends Request
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/test';
            }
        }
    );
}

function saloonResponse(PendingRequest $pendingRequest): Response
{
    return new Response(psr7Response(), $pendingRequest, $pendingRequest->createPsrRequest());
}

it('resolves the psr request from Saloon\'s SendingSaloonRequest event', function () {
    $psrRequest = EventHelper::getPsrRequest(new SendingSaloonRequest(saloonPendingRequest()));

    expect($psrRequest)->toBeInstanceOf(Psr7Request::class)
        ->and($psrRequest->getMethod())->toBe('GET')
        ->and($psrRequest->getUri()->__toString())->toBe('http://localhost/test');
});

it('resolves the psr request from Saloon\'s SentSaloonRequest event', function () {
    $pendingRequest = saloonPendingRequest();
    $psrRequest = EventHelper::getPsrRequest(new SentSaloonRequest($pendingRequest, saloonResponse($pendingRequest)));

    expect($psrRequest)->toBeInstanceOf(Psr7Request::class)
        ->and($psrRequest->getMethod())->toBe('GET')
        ->and($psrRequest->getUri()->__toString())->toBe('http://localhost/test');
});

it('resolves the psr response from Saloon\'s SentSaloonRequest event', function () {
    $pendingRequest = saloonPendingRequest();
    $psrResponse = EventHelper::getPsrResponse(new SentSaloonRequest($pendingRequest, saloonResponse($pendingRequest)));

    expect($psrResponse)->toBeInstanceOf(Psr7Response::class)
        ->and($psrResponse->getStatusCode())->toBe(200)
        ->and($psrResponse->getHeaderLine('X-Foo'))->toBe('Bar');
});
