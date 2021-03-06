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

(look into `config/http-client-global-logger.php` for further configuration and explanation)

## Features

Using the logger will log both the request and response of an external HTTP request made with the [Laravel HTTP Client](https://laravel.com/docs/http-client).

- Multi-line log records that contain full request/response information (including all headers and body)
- Logging into separate logfile `http-client.log`. You're free to override this and use your own logging channel or just log to a different logfile.
- Full support of [Guzzle MessageFormatter](https://github.com/guzzle/guzzle/blob/master/src/MessageFormatter.php) variable substitutions for highly customized log messages.
- **Variant 1: Global logging** (default)
  - Zero-configuration: Global logging is enabled by default in this package.
  - Simple and performant implementation using `RequestSending` / `ResponseReceived` event listeners
  - Obfuscation of common credentials passed in request (e.g. `Authorization` header's Bearer token)
- **Variant 2: Mixin** (`HTTP_CLIENT_GLOBAL_LOGGER_MIXIN=true`)
  - Enabled only on individual HTTP Client instances, using `Http::log()` - no global logging.
  - Log channel name can be set per HTTP Client instance by passing a name to `Http::log($name)`

## Usage

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
  - obfuscation of credentials in HTTP Client requests
- [bilfeldt/laravel-http-client-logger](https://github.com/bilfeldt/laravel-http-client-logger)
  - conditional logging using `logWhen($condition)`
  - filtering of logs by HTTP response codes
  - currently still supports PHP 7.4+

So, my recommendation: If you need global logging without any extra configuration and without changing a line of code in your project, go for my package. If you don't want to log everything and wish to filter by HTTP response code, go for [Bilfeldt](https://github.com/bilfeldt)'s package. **But don't install both!**

## Caveats

- This package currently uses two different implementations for logging. In the preferred variant 1 (global logging), it is currently not possible to configure the [log channel name](https://laravel.com/docs/logging#configuring-the-channel-name) which defaults to current environment, such as `production` or `local`. If you with to use Laravel HTTP Client to access multiple different external APIs, it is nice to explicitely distinguish between them by different log channel names.
  
  As a workaround, I have implemented another way of logging through `Http::log()` method as mixin. But of course, we should combine both variants into a single one for a cleaner codebase.
  
- Very basic obfuscation support using regex with lookbehind assertions (e.g. `/(?<=Authorization:\sBearer ).*/m`, modifying formatted log output. It's currently not possible to directly modify request headers or JSON data in request body.

- Obfuscation currently only works in variant 1 (global logging).

## Testing

TBD.

(any help appreciated!)

## Changes

All changes are listed in [CHANGELOG](CHANGELOG.md)

## Authors

Author of this shitty little package is **[Philip Iezzi (Onlime GmbH)](https://www.onlime.ch/)**.

## License

This package is licenced under the [MIT license](LICENSE) however support is more than welcome.
