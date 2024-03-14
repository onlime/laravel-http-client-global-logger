<?php

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Event;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogRequestSending;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogResponseReceived;
use Onlime\LaravelHttpClientGlobalLogger\Providers\ServiceProvider;
use Saloon\Laravel\Events\SendingSaloonRequest;
use Saloon\Laravel\Events\SentSaloonRequest;

it('registers event listeners', function (string $eventName, string $listenerName) {
    config([
        'http-client-global-logger.enabled' => true,
        'http-client-global-logger.mixin' => false,
    ]);

    (new ServiceProvider(app()))->register();

    $listeners = Event::getRawListeners()[$eventName] ?? [];

    expect($listeners)->not->toBeEmpty()
        ->and($listeners)->toContain($listenerName);
})->with([
    [RequestSending::class, LogRequestSending::class],
    [ResponseReceived::class, LogResponseReceived::class],
    [SendingSaloonRequest::class, LogRequestSending::class],
    [SentSaloonRequest::class, LogResponseReceived::class],
]);

it('merges the default config', function () {
    $config = config('http-client-global-logger');

    expect($config)->toBeArray();

    foreach ([
        'enabled',
        'mixin',
        'channel',
        'logfile',
        'format',
        'obfuscate',
        'trim_response_body',
    ] as $key) {
        expect($config)->toHaveKey($key);
    }
});

it('can publish the config file', function () {
    @unlink(config_path('http-client-global-logger.php'));

    $this->artisan('vendor:publish', ['--tag' => 'http-client-global-logger']);

    $this->assertFileExists(config_path('http-client-global-logger.php'));
});

it('configures the log channel', function () {
    $defaultChannel = config('http-client-global-logger.channel');

    $config = config('logging.channels.'.$defaultChannel);

    expect($config)->toBeArray();
});

it('can register the mixin on the PendingRequest class', function (bool $mixin) {
    PendingRequest::flushMacros();

    config([
        'http-client-global-logger.mixin' => $mixin,
    ]);

    (new ServiceProvider(app()))->boot();

    expect(PendingRequest::hasMacro('log'))->toBe($mixin);
})->with([
    true,
    false,
]);
