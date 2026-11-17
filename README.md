# Laravel HTTP Client Global Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/onlime/laravel-http-client-global-logger.svg)](https://packagist.org/packages/onlime/laravel-http-client-global-logger)
[![Packagist Downloads](https://img.shields.io/packagist/dt/onlime/laravel-http-client-global-logger.svg)](https://packagist.org/packages/onlime/laravel-http-client-global-logger)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/onlime/laravel-http-client-global-logger.svg)](https://packagist.org/packages/onlime/laravel-http-client-global-logger)
[![Build Status](https://github.com/onlime/laravel-http-client-global-logger/actions/workflows/ci.yml/badge.svg)](https://github.com/onlime/laravel-http-client-global-logger/actions/workflows/ci.yml)
[![GitHub License](https://img.shields.io/github/license/onlime/laravel-http-client-global-logger.svg)](https://github.com/onlime/laravel-http-client-global-logger/blob/main/LICENSE)

A super simple global logger for the [Laravel HTTP Client](https://laravel.com/docs/http-client).

## Requirements

- PHP 8.3+ (tested up to PHP 8.6)
- Laravel 12 or 13

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
- `HTTP_CLIENT_GLOBAL_LOGGER_EXCEPT` (string, comma-separated URL patterns with wildcard support)
- `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_ENABLED` (bool)
- `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_REPLACEMENT` (string)
- `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_HEADERS` (string, comma-separated header name patterns with wildcard support)
- `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_BODY_KEYS` (string, comma-separated key patterns with wildcard support)
- `HTTP_CLIENT_GLOBAL_LOGGER_TRIM_RESPONSE_BODY_ENABLED` (bool)
- `HTTP_CLIENT_GLOBAL_LOGGER_TRIM_RESPONSE_BODY_LIMIT` (int)
- `HTTP_CLIENT_GLOBAL_LOGGER_TRIM_RESPONSE_BODY_CONTENT_TYPE_WHITELIST` (string)

(look into `config/http-client-global-logger.php` for defaults, further configuration, and explanation)

## Features

Using the logger will log both the request and response of an external HTTP request made with the [Laravel HTTP Client](https://laravel.com/docs/http-client).

- Multi-line log records that contain full request/response information (including all headers and body)
- Logging into separate logfile `http-client.log`. You're free to override this and use your own logging channel or just log to a different logfile.
- Full support of [Guzzle MessageFormatter](https://github.com/guzzle/guzzle/blob/master/src/MessageFormatter.php) variable substitutions for highly customized log messages.
- Obfuscation of credentials in headers, URL and body (JSON or form), see [Sensitive data](#sensitive-data)
- URL exclusion patterns to skip logging for specific URLs (e.g. `HTTP_CLIENT_GLOBAL_LOGGER_EXCEPT=https://api.pirsch.io/*,https://sentry.io/*`)
- Trimming of response body content to a certain length with support for `Content-Type` whitelisting
- Enforce trimming of response body content by setting a `X-Global-Logger-Trim-Always` request header, which will ignore the whitelisting.
- **Variant 1: Global logging** (default)
  - Zero-configuration: Global logging is enabled by default in this package.
  - Simple and performant implementation using `RequestSending` / `ResponseReceived` event listeners
  - Also logs [Saloon](https://docs.saloon.dev/) requests if [saloonphp/laravel-plugin](https://github.com/saloonphp/laravel-plugin) is installed
- **Variant 2: Mixin** (`HTTP_CLIENT_GLOBAL_LOGGER_MIXIN=true`)
  - Enabled only on individual HTTP Client instances, using `Http::log()` - no global logging.
  - Log channel name can be set per HTTP Client instance by passing a name to `Http::log($name)`
- **Variant 3: Global HTTP Middleware**
  - Can be used in combination with other `Http::globalRequestMiddleware()` calls in your `AppServiceProvider`'s `boot()` method, after registering your [Global Middleware](https://laravel.com/docs/http-client#global-middleware).


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

If you use [Global Middleware](https://laravel.com/docs/http-client#global-middleware) (`Http::globalRequestMiddleware()` and `Http::globalResponseMiddleware()` methods), you should be aware that *Variant 1* uses Laravel's `RequestSending` event to log HTTP requests. This event is fired **before** Global Middleware is executed. Therefore, any modifications to the request made by Global Middleware will not be logged. To overcome this, this package provides a middleware that you may add after your Global Middleware.

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
Authorization: [redacted]
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

## Sensitive data

The log contains full requests and responses, including all headers and bodies. Credentials are obfuscated by default (`HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_ENABLED`), in all 3 variants, replaced by `[redacted]` (`HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_REPLACEMENT`):

- **Headers** of requests and responses whose name matches `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_HEADERS`, defaults to `*authorization,*cookie,*password*,*token*,*key,*secret*`. E.g. `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Password`, `PRIVATE-TOKEN` or `X-Api-Key`. Body keys in the values of other headers are obfuscated too, e.g. `X-Debug: token=[redacted]`.
- **Values of body keys** whose name matches `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_BODY_KEYS`, defaults to `pass,*password*,*token*,*secret*,*key,*signature*,sig`. They are replaced in JSON-style (`"key":"value"`) and form/query-style (`key=value`) text of the formatted message, which covers JSON and form bodies, and the query string of the URL. E.g. `{"password":"[redacted]"}`, `refresh_token=[redacted]` or `?api_key=[redacted]`.
- **The password in the URL**, e.g. `https://user:[redacted]@example.com`.
- **Password hashes** in the modular crypt format (`$<identifier>$…`), anywhere except for their algorithm identifier, also in the values of the body keys above, like in [onlime/laravel-sql-reporter](https://github.com/onlime/laravel-sql-reporter). E.g. `$2y$[redacted]` (bcrypt), `$argon2id$[redacted]`, or legacy ones like `$6$[redacted]` (SHA-512-crypt) or `$P$[redacted]` (phpass).

Patterns are comma-separated, case-insensitive, and support wildcards using `*` (see [`Str::is()`](https://laravel.com/docs/strings#method-str-is)). A wildcard also matches the brackets of nested form keys, e.g. `user[password]` for `*password*`.

Not obfuscated:

- Keys and headers with other names. Add them to the patterns above.
- Other body formats, e.g. XML or `multipart/form-data`. Exclude such URLs from logging with `HTTP_CLIENT_GLOBAL_LOGGER_EXCEPT`.
- JSON values other than strings, e.g. `{"pin":1234}` or `{"tokens":["…"]}`, and JSON within a JSON string, e.g. `{"payload":"{\"password\":\"…\"}"}`.
- Secrets in the path of the URL, or a token as its user name without password, e.g. `https://TOKEN@example.com`.
- URL-encoded password hashes (`%242y%24…`) in the values of other keys.

Protect the log file like any other log of your application: it is created with the same permissions as Laravel's own logs. If logging fails, e.g. because the log file isn't writable, the exception is reported and the HTTP request is sent as usual. This applies to the global logging variants 1 and 3, while the mixin variant (`Http::log()`) throws the exception.

## Upgrading

### v1.6.0

The defaults of `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_HEADERS`, `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_BODY_KEYS` and `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_REPLACEMENT` have changed. If you set any of them in your `.env`, or published the config file, compare them with the new defaults in [`config/http-client-global-logger.php`](config/http-client-global-logger.php), as your values replace them. Matching is case-insensitive now, with wildcard support, so your existing names keep working.

## Testing

We're using [Pest](https://pestphp.com/) for testing, [Larastan](https://github.com/larastan/larastan) for static analysis, and [Pint](https://laravel.com/docs/pint) for code style:

```bash
$ composer test           # run the test suite
$ composer test-coverage  # run the test suite with coverage report (requires pcov or Xdebug)
$ composer analyse        # run static analysis
$ composer lint           # check code style (use `composer format` to fix it)
```

## Changes

All changes are listed in [CHANGELOG](CHANGELOG.md)

## Authors

Made with ❤️ by **[Philip Iezzi (Onlime GmbH)](https://www.onlime.ch/)**.

## License

This package is licenced under the [MIT license](LICENSE) however support is more than welcome.
