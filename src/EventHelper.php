<?php

namespace Onlime\LaravelHttpClientGlobalLogger;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Laravel\Events\SendingSaloonRequest;
use Saloon\Laravel\Events\SentSaloonRequest;

class EventHelper
{
    public static function getPsrRequest(object $event): RequestInterface
    {
        return match (true) {
            $event instanceof RequestSending || $event instanceof ResponseReceived => $event->request->toPsrRequest(),
            $event instanceof SendingSaloonRequest || $event instanceof SentSaloonRequest => $event->pendingRequest->createPsrRequest(),
            default => throw new InvalidArgumentException('Cannot get PSR Request from Event: '.$event::class),
        };
    }

    public static function getPsrResponse(object $event): ResponseInterface
    {
        return match (true) {
            $event instanceof ResponseReceived => $event->response->toPsrResponse(),
            $event instanceof SentSaloonRequest => $event->response->getPsrResponse(),
            default => throw new InvalidArgumentException('Cannot get PSR Response from Event: '.$event::class),
        };
    }
}
