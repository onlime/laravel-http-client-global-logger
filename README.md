# Laravel HTTP Client Global Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/onlime/laravel-http-client-global-logger.svg)](https://packagist.org/packages/onlime/laravel-http-client-global-logger)
[![Packagist Downloads](https://img.shields.io/packagist/dt/onlime/laravel-http-client-global-logger.svg)](https://packagist.org/packages/onlime/laravel-http-client-global-logger)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/onlime/laravel-http-client-global-logger.svg)](https://packagist.org/packages/onlime/laravel-http-client-global-logger)
[![GitHub License](https://img.shields.io/github/license/onlime/laravel-http-client-global-logger.svg)](https://github.com/onlime/laravel-http-client-global-logger/blob/main/LICENSE)

A super simple global logger for the [Laravel HTTP Client](https://laravel.com/docs/http-client).

## Installation

You can install the package via Composer:

```bash
$ composer require onlime/laravel-http-client-global-logger
```

## Configuration

This is a zero-configuration package. It is auto-discovered by Laravel and global logging is enabled by default. **No further configuration needed - you may skip directly to the [Usage](#usage) section below.**

Optionally publish the config file with:

 ```bash
 $ php artisan vendor:publish --provider="Onlime\LaravelHttpClientGlobalLogger\Providers\ServiceProvider"
 ```

You may override its configuration in your `.env` - the following environment vars are supported:

- `HTTP_CLIENT_GLOBAL_LOGGER_ENABLED` (bool)
- `HTTP_CLIENT_GLOBAL_LOGGER_MIXIN` (bool)
- `HTTP_CLIENT_GLOBAL_LOGGER_CHANNEL` (string)
- `HTTP_CLIENT_GLOBAL_LOGGER_LOGFILE` (string)
- `HTTP_CLIENT_GLOBAL_LOGGER_REQUEST_FORMAT` (string)
- `HTTP_CLIENT_GLOBAL_LOGGER_RESPONSE_FORMAT` (string)
- `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_ENABLED` (bool)
- `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_REPLACEMENT` (string)
- `HTTP_CLIENT_GLOBAL_LOGGER_TRIM_RESPONSE_BODY_ENABLED` (bool)
- `HTTP_CLIENT_GLOBAL_LOGGER_TRIM_RESPONSE_BODY_TRESHOLD` (int)
- `HTTP_CLIENT_GLOBAL_LOGGER_TRIM_RESPONSE_BODY_CONTENT_TYPE_WHITELIST` (string)

(look into `config/http-client-global-logger.php` for further configuration and explanation)

## Features

Using the logger will log both the request and response of an external HTTP request made with the [Laravel HTTP Client](https://laravel.com/docs/http-client).

- Multi-line log records that contain full request/response information (including all headers and body)
- Logging into separate logfile `http-client.log`. You're free to override this and use your own logging channel or just log to a different logfile.
- Full support of [Guzzle MessageFormatter](https://github.com/guzzle/guzzle/blob/master/src/MessageFormatter.php) variable substitutions for highly customized log messages.
- Basic obfuscation of credentials in HTTP Client requests
- Trimming of response body content to a certain length with support for Content-Type whitelisting
- **Variant 1: Global logging** (default)
  - Zero-configuration: Global logging is enabled by default in this package.
  - Simple and performant implementation using `RequestSending` / `ResponseReceived` event listeners
  - Obfuscation of common credentials passed in request (e.g. `Authorization` header's Bearer token)
- **Variant 2: Mixin** (`HTTP_CLIENT_GLOBAL_LOGGER_MIXIN=true`)
  - Enabled only on individual HTTP Client instances, using `Http::log()` - no global logging.
  - Log channel name can be set per HTTP Client instance by passing a name to `Http::log($name)`
- **Variant 3: Global HTTP Middleware**
  - Can be used in combination with other `Http::globalRequestMiddleware()` calls in your `AppServiceProvider`'s `boot()` method, after registering your [Global Middleware](https://laravel.com/docs/10.x/http-client#global-middleware).


## Usage

> **NOTE:** For all 3 variants below, you need to keep the HTTP Client Global Logger enabled (not setting `HTTP_CLIENT_GLOBAL_LOGGER_ENABLED=false` in your `.env`). The `http-client-global-logger.enabled` config option is a global on/off switch for all 3 variants, not just the "global" variants. Our project name might be misleading in that context.

### Variant 1: Global Logging

**Just use Laravel HTTP Client as always - no need to configure anything!**

```php
Http::get('https://example.com');
```

Slightly more complex example:

```php
$client = Http::withOptions([
    'base_uri'        => 'https://example.com',
    'allow_redirects' => false,
]);
$response = $client->get('/user');
```

### Variant 2: Mixin Variant

If you enable mixin variant, global logging will be turned off. Put this into your `.env`:

```ini
HTTP_CLIENT_GLOBAL_LOGGER_MIXIN=true
```

You could then turn on logging individually on each HTTP Client instance, using the `log()` method:

```php
Http::log()->get('https://example.com');
```

Logging with custom channel name (if not specified, defaults to current environment, such as `production` or `local`):

```php
Http::log('my-api')->get('https://example.com');
```

Slightly more complex example:

```php
$client = Http::log('my-api')->withOptions([
    'base_uri'        => 'https://example.com',
    'allow_redirects' => false,
]);
$response = $client->get('/user');
```

### Variant 3: Global HTTP Middleware

If you use [Global Middleware](https://laravel.com/docs/10.x/http-client#global-middleware) (`Http::globalRequestMiddleware()` and `Http::globalResponseMiddleware()` methods), you should be aware that *Variant 1* uses Laravel's `RequestSending` event to log HTTP requests. This event is fired **before** Global Middleware is executed. Therefore, any modifications to the request made by Global Middleware will not be logged. To overcome this, this package provides a middleware that you may add after your Global Middleware.

You may add the middleware using the static `addRequestMiddleware()` method on the `HttpClientLogger` class:

```php
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;

HttpClientLogger::addRequestMiddleware();
```

For example, you may add this to your `AppServiceProvider`'s `boot()` method after registering your Global Middleware:

```php
use Illuminate\Support\Facades\Http;
use Onlime\LaravelHttpClientGlobalLogger\HttpClientLogger;

Http::globalRequestMiddleware(fn ($request) => $request->withHeader(
    'User-Agent', 'My Custom User Agent'
));

HttpClientLogger::addRequestMiddleware();
```

## Logging example

By default, logs are written to a separate logfile `http-client.log`.

Log entry example:

```
[2021-07-11 11:29:58] local.INFO: REQUEST: GET https://example.com/user
GET /user HTTP/1.1
User-Agent: GuzzleHttp/7
Host: example.com
Authorization: Bearer *******************
[2021-07-11 11:29:58] local.INFO: RESPONSE: HTTP/1.1 200 OK
HTTP/1.1 200 OK
Date: Fri, 18 Jun 2021 09:29:58 GMT
Server: nginx
Content-Type: application/json
{"username":"foo","email":"foo@example.com"}
```

## FAQ

### How does this package differ from `bilfeldt/laravel-http-client-logger` ?

Honestly, I did not really look into [bilfeldt/laravel-http-client-logger](https://github.com/bilfeldt/laravel-http-client-logger), as my primary goal was to build a global logger for Laravel HTTP Client without any added bulk. Global logging currently (as of July 2021) is still an open issue, see [bilfeldt/laravel-http-client-logger#2 - Add global logging](https://github.com/bilfeldt/laravel-http-client-logger/issues/2).

Both packages provide a different feature set and have those advantages:

- [onlime/laravel-http-client-global-logger](https://github.com/onlime/laravel-http-client-global-logger) (this package)
  - global logging
  - auto-configured log channel `http-client` to log to a separate `http-client.log` file
  - Full support of [Guzzle MessageFormatter](https://github.com/guzzle/guzzle/blob/master/src/MessageFormatter.php) variable substitutions for highly customized log messages.
  - basic obfuscation of credentials in HTTP Client requests
  - trimming of response body content
- [bilfeldt/laravel-http-client-logger](https://github.com/bilfeldt/laravel-http-client-logger)
  - conditional logging using `logWhen($condition)`
  - filtering of logs by HTTP response codes
  - currently still supports PHP 7.4+

So, my recommendation: If you need global logging without any extra configuration and without changing a line of code in your project, go for my package. If you don't want to log everything and wish to filter by HTTP response code, go for [Bilfeldt](https://github.com/bilfeldt)'s package. **But don't install both!**

## Caveats

- This package currently uses two different implementations for logging. In the preferred variant 1 or 3 (global logging), it is currently not possible to configure the [log channel name](https://laravel.com/docs/logging#configuring-the-channel-name) which defaults to current environment, such as `production` or `local`. If you with to use Laravel HTTP Client to access multiple different external APIs, it is nice to explicitly distinguish between them by different log channel names.

  As a workaround, I have implemented another way of logging through `Http::log()` method as mixin. But of course, we should combine both variants into a single one for a cleaner codebase.

- Obfuscation

  - Body keys: Very basic obfuscation support using regex with lookbehind assertions (e.g. `/(?<="token":").*(?=")/mU`, modifying formatted log output. It's currently not possible to directly modify JSON data in request body.

  - No obfuscation of query params, e.g. on a POST request to an OAuth2 token endpoint.

  - Obfuscation currently only works in variant 1 or 3 (global logging), and only on requests, not yet on response data.


## Testing

Currently, there is very basic code/test coverage. We're using [PEST](https://pestphp.com/), so just run all tests like so:

```bash
$ ./vendor/bin/pest
```

## Changes

All changes are listed in [CHANGELOG](CHANGELOG.md)

## Authors

Made with ❤️ by **[Philip Iezzi (Onlime GmbH)](https://www.onlime.ch/)**.

## License

This package is licenced under the [MIT license](LICENSE) however support is more than welcome.
