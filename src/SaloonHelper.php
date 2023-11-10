<?php

namespace Onlime\LaravelHttpClientGlobalLogger;

use Saloon\HttpSender\HttpSender;
use Saloon\Laravel\Events\SendingSaloonRequest;
use Saloon\Laravel\Events\SentSaloonRequest;

class SaloonHelper
{
    public static function shouldBeLogged($event): bool
    {
        if ($event instanceof SendingSaloonRequest || $event instanceof SentSaloonRequest) {
            $saloonUsesHttpSender = config('saloon.default_sender') === HttpSender::class;

            return $event->pendingRequest->hasMockClient() || ! $saloonUsesHttpSender;
        }

        return true;
    }
}
