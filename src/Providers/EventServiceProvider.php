<?php

namespace Onlime\LaravelHttpClientGlobalLogger\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogRequestSending;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogResponseReceived;
use Saloon\Laravel\Events\SendingSaloonRequest;
use Saloon\Laravel\Events\SentSaloonRequest;

class EventServiceProvider extends BaseEventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // Laravel HTTP Client
        RequestSending::class => [
            LogRequestSending::class,
        ],
        ResponseReceived::class => [
            LogResponseReceived::class,
        ],

        // Saloon
        SendingSaloonRequest::class => [
            LogRequestSending::class,
        ],
        SentSaloonRequest::class => [
            LogResponseReceived::class,
        ],
    ];

    /**
     * Get the events and handlers.
     *
     * @return array
     */
    public function listens()
    {
        return config('http-client-global-logger.enabled') ? $this->listen : [];
    }
}
