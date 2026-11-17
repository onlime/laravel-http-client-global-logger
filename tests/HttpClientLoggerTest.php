<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogRequestSending;
use Onlime\LaravelHttpClientGlobalLogger\Listeners\LogResponseReceived;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Saloon\Enums\Method;
use Saloon\Http\Auth\QueryAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest as SaloonPendingRequest;
use Saloon\Http\Request as SaloonRequest;
use Saloon\Http\Response as SaloonResponse;
use Saloon\Laravel\Events\SendingSaloonRequest;
use Saloon\Laravel\Events\SentSaloonRequest;

function setupLogger(): MockInterface
{
    HttpClientLogger::addRequestMiddleware();

    $logger = Mockery::mock(LoggerInterface::class);

    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);

    // Rethrow what the listeners report, e.g. a failed expectation, instead of logging it
    Log::shouldReceive('error')->andReturnUsing(fn (string $message, array $context) => throw $context['exception']);

    return $logger;
}

it('can add a global request middleware to log the requests', function () {
    Http::globalRequestMiddleware(
        fn (RequestInterface $psrRequest) => $psrRequest->withHeader('X-Test', 'test')
    );

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)
            ->toContain('REQUEST: GET https://example.com')
            ->and($message)
            ->toContain('X-Test: test');

        return true;
    })->once()->andReturnSelf();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)
            ->toContain('RESPONSE: HTTP/1.1 200 OK');

        return true;
    })->once()->andReturnSelf();

    Http::fake()->get('https://example.com');
});

it('can trim the response body', function (array $config, string $contentType, bool $shouldTrim, bool $addCharsetToContentType) {
    config(['http-client-global-logger.trim_response_body' => $config]);

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('REQUEST: GET https://example.com');
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($shouldTrim) {
        expect($message)->toContain($shouldTrim ? 'verylongbo...' : 'verylongbody');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('verylongbody', 200, [
            'Content-Type' => $contentType.($addCharsetToContentType ? '; charset=UTF-8' : ''),
        ]),
    ])->get('https://example.com');
})->with(
    [
        'disabled' =>  [
            'config' => [
                'enabled' => false,
                'limit' => 10,
                'content_type_whitelist' => ['application/json'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => false,
        ],
        'below_limit' => [
            'config' => [
                'enabled' => true,
                'limit' => 20,
                'content_type_whitelist' => ['application/json'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => false,
        ],
        'content_type_whitelisted' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['application/octet-stream'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => false,
        ],
        'trim' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['application/json'],
            ],
            'contentType' => 'application/octet-stream',
            'shouldTrim' => true,
        ],
        'no_content_type_trim' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['application/octet-stream'],
            ],
            'contentType' => '',
            'shouldTrim' => true,
        ],
        'no_content_type_whitelisted' => [
            'config' => [
                'enabled' => true,
                'limit' => 10,
                'content_type_whitelist' => ['', 'application/octet-stream'],
            ],
            'contentType' => '',
            'shouldTrim' => false,
        ],
    ],
    [
        'with-charset' => ['addCharsetToContentType' => true],
        'without-charset' => ['addCharsetToContentType' => false],
    ]
);

it('always trims the response body when special header is set on request', function (bool $withHeader) {
    config(['http-client-global-logger.trim_response_body' => [
        'enabled' => true,
        'limit' => 10,
        'content_type_whitelist' => ['application/json'],
    ]]);

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('REQUEST: GET https://example.com');
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($withHeader) {
        expect($message)->toContain($withHeader ? 'verylongbo...' : 'verylongbody');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('verylongbody', 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->withHeader($withHeader ? 'X-Global-Logger-Trim-Always' : 'X-Dummy', 'true')
        ->get('https://example.com');
})->with([true, false]);

it('obfuscates request header and body', function (string $body, string $expected) {
    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain("REQUEST: POST https://example.com\r\n")
            ->and($message)->toContain("Authorization: [redacted]\r\n")
            ->and($message)->toContain($expected);
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain("RESPONSE: HTTP/1.1 200 OK\r\n");
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->withHeader('Authorization', 'Bearer 123')
        ->withBody($body)
        ->post('https://example.com');
})->with([
    'json-style' => [
        '{"name":"value","apikey":"s3cr3tK3y","token":"s0meT0k3n"}',
        '{"name":"value","apikey":"[redacted]","token":"[redacted]"}',
    ],
    // OpenID Connect example for POST /token endpoint
    // see https://openid.net/specs/openid-connect-core-1_0.html#RefreshingAccessToken
    'form-style-openid' => [
        'grant_type=refresh_token&refresh_token=r3fr3shT0k3n&client_id=1234&client_secret=53cr3t',
        'grant_type=refresh_token&refresh_token=[redacted]&client_id=1234&client_secret=[redacted]',
    ],
]);

it('obfuscates request uri via body obfuscation', function (string $uri, string $expected) {
    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain("REQUEST: GET $expected\r\n")
            ->and(substr_count($message, "\r\n"))->toBeGreaterThan(1);
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('RESPONSE: HTTP/1.1 200 OK');
        return true;
    })->once();

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->get($uri);
})->with([
    'no-query-params' => [
        'https://example.com',
        'https://example.com',
    ],
    'sensitive-query-param' => [
        'https://example.com?access_key=1234',
        'https://example.com?access_key=[redacted]',
    ],
    'other-query-param' => [
        'https://example.com?foo=bar&baz=qux',
        'https://example.com?foo=bar&baz=qux',
    ],
    'mixed-query-params' => [
        'https://example.com?token=xyz&foo=bar&access_key=1234',
        'https://example.com?token=[redacted]&foo=bar&access_key=[redacted]',
    ],
]);

it('excludes URLs matching except patterns', function () {
    config(['http-client-global-logger.except' => ['https://api.pirsch.io/*']]);

    $logger = setupLogger();

    $logger->shouldNotReceive('info');

    Http::fake()->get('https://api.pirsch.io/api/v1/hit');
});

it('logs URLs not matching except patterns', function () {
    config(['http-client-global-logger.except' => ['https://api.pirsch.io/*']]);

    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('REQUEST: GET https://example.com');
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) {
        expect($message)->toContain('RESPONSE: HTTP/1.1 200 OK');
        return true;
    })->once();

    Http::fake()->get('https://example.com');
});

it('supports multiple except patterns', function () {
    config(['http-client-global-logger.except' => [
        'https://api.pirsch.io/*',
        'https://sentry.io/*',
    ]]);

    $logger = setupLogger();

    $logger->shouldNotReceive('info');

    Http::fake()->get('https://sentry.io/api/0/envelope');
});

it('obfuscates response body', function (string $body, string $expected) {
    $logger = setupLogger();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain('REQUEST: POST https://example.com')
            ->and($message)->toContain($expected);
        return true;
    })->once();

    $logger->shouldReceive('info')->withArgs(function ($message) use ($expected) {
        expect($message)->toContain('RESPONSE: HTTP/1.1 200 OK')
            ->and($message)->toContain($expected);
        return true;
    })->once();

    Http::fake([
        '*' => Http::response($body, 200, [
            'Content-Type' => 'application/json',
        ]),
    ])->withBody($body)
        ->post('https://example.com');
})->with([
    'json-style-openid' => [
        '{"access_token":"s0meT0k3n-1","expires_in":300,"refresh_token":"s0meT0k3n-2","token_type":"Bearer","id_token":"s0meT0k3n-3","session_state":"1234-56","scope":"foo bar"}',
        '{"access_token":"[redacted]","expires_in":300,"refresh_token":"[redacted]","token_type":"[redacted]","id_token":"[redacted]","session_state":"1234-56","scope":"foo bar"}',
    ],
    'form-style' => [
        'grant_type=refresh_token&refresh_token=r3fr3shT0k3n&client_id=1234&client_secret=53cr3t',
        'grant_type=refresh_token&refresh_token=[redacted]&client_id=1234&client_secret=[redacted]',
    ],
]);

it('reports a logging failure without breaking the request', function () {
    Exceptions::fake();

    // An unwritable log file, e.g. one created by root
    config(['logging.channels.http-client.with.stream' => '/dev/null/http-client.log']);
    Log::forgetChannel('http-client');

    $response = Http::fake()->get('https://example.com');

    expect($response->ok())->toBeTrue();
    Exceptions::assertReported(UnexpectedValueException::class);
});

/**
 * Capture the messages logged to the http-client channel.
 *
 * @return ArrayObject<int, string>
 */
function captureLog(): ArrayObject
{
    $messages = new ArrayObject;
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->andReturnUsing(function (string $message) use ($messages) {
        $messages[] = $message;
    });
    Log::shouldReceive('channel')->with('http-client')->andReturn($logger);
    Log::shouldReceive('error')->andReturnUsing(fn (string $message, array $context) => throw $context['exception']);

    return $messages;
}

it('logs the obfuscated request and response with the event listeners', function () {
    $messages = captureLog();

    Http::fake(['*' => Http::response('{"access_token":"s3cr3t-1"}', 200, ['Set-Cookie' => 'session=s3cr3t-2'])])
        ->withToken('s3cr3t-3')
        ->post('https://example.com/?token=s3cr3t-4', ['password' => 's3cr3t-5']);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toStartWith('REQUEST: POST https://example.com/?token=[redacted]')
        ->and($messages[0])->toContain('Authorization: [redacted]')
        ->and($messages[1])->toStartWith('RESPONSE: HTTP/1.1 200 OK')
        ->and($messages[1])->toContain('Set-Cookie: [redacted]')
        ->and(implode("\n", $messages->getArrayCopy()))->not->toContain('s3cr3t');
});

it('excludes URLs matching except patterns with the event listeners', function () {
    config(['http-client-global-logger.except' => ['https://api.pirsch.io/*']]);
    $messages = captureLog();

    Http::fake()->get('https://api.pirsch.io/api/v1/hit');

    expect($messages)->toHaveCount(0);
});

it('logs obfuscated Saloon requests and responses', function () {
    $messages = captureLog();

    $pendingRequest = new SaloonPendingRequest(
        new class extends Connector
        {
            public function resolveBaseUrl(): string
            {
                return 'https://api.example.com';
            }

            protected function defaultAuth(): QueryAuthenticator
            {
                return new QueryAuthenticator('apiKey', 's3cr3t-1');
            }

            protected function defaultHeaders(): array
            {
                return ['X-Api-Key' => 's3cr3t-2'];
            }
        },
        new class extends SaloonRequest
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/users';
            }
        }
    );
    $psrResponse = new Psr7Response(200, ['Set-Cookie' => 'session=s3cr3t-3'], '{"password":"s3cr3t-4"}');

    (new LogRequestSending)->handle(new SendingSaloonRequest($pendingRequest));
    (new LogResponseReceived)->handle(new SentSaloonRequest(
        $pendingRequest,
        new SaloonResponse($psrResponse, $pendingRequest, $pendingRequest->createPsrRequest())
    ));

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toStartWith('REQUEST: GET https://api.example.com/users?apiKey=[redacted]')
        ->and($messages[1])->toContain('{"password":"[redacted]"}')
        ->and(implode("\n", $messages->getArrayCopy()))->not->toContain('s3cr3t');
});

it('does not add the request middleware if disabled', function () {
    config(['http-client-global-logger.enabled' => false]);

    HttpClientLogger::addRequestMiddleware();

    expect(HttpClientLogger::requestMiddlewareWasAdded())->toBeFalse();
});

it('does not log the requests made while reporting a logging failure', function () {
    // An unwritable log file, and an error tracker reporting the failure with an HTTP request
    config(['logging.channels.http-client.with.stream' => '/dev/null/http-client.log']);
    Log::forgetChannel('http-client');

    $reported = 0;
    app(ExceptionHandler::class)->reportable(function (UnexpectedValueException $e) use (&$reported) {
        // Limited, so that an endless recursion fails the test instead of exhausting the memory
        if (++$reported <= 10) {
            Http::post('https://errors.example.com', ['message' => $e->getMessage()]);
        }

        return false;
    });

    $response = Http::fake()->get('https://example.com');

    // Once for the request, once for the response, not for the error tracker's request
    expect($response->ok())->toBeTrue()
        ->and($reported)->toBe(2);
});

it('does not break the request if reporting a logging failure fails too', function () {
    // An unwritable log file, and a reporting that fails too, e.g. with an unwritable log directory
    config(['logging.channels.http-client.with.stream' => '/dev/null/http-client.log']);
    Log::forgetChannel('http-client');
    app(ExceptionHandler::class)->reportable(fn (UnexpectedValueException $e) => throw new RuntimeException('Reporting failed'));

    expect(Http::fake()->get('https://example.com')->ok())->toBeTrue();
});
