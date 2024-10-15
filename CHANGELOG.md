# CHANGELOG

## [v1.2.x (Unreleased)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.2.0...main)

## [v1.2.0 (2024-10-15)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.1.3...v1.2.0)

- Drop PHP 8.1 support
- Upgrade pestphp/pest to v3

## [v1.1.3 (2024-03-14)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.1.2...v1.1.3)

- Added GitHub Actions workflow, Improve test suite by @pascalbaljet in #4
- Bumped `saloonphp/laravel-http-sender` dependency to support Laravel 11
- Bumped Laravel 10 requirement to `^10.32` as it requires `getGlobalMiddleware()`
- Removed unneeded `guzzlehttp/guzzle` dependency
- Streamlined `EventServiceProvider` into `ServiceProvider`
- Added tests for `EventHelper`, `PendingRequestMixin` and `ServiceProvider`
- The added Guzzle middleware now has a name for easier debugging and testing

## [v1.1.2 (2024-02-16)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.1.1...v1.1.2)

- Feature | Support for trimming the response body by @pascalbaljet in #3
- Laravel 11 support

## [v1.1.1 (2023-11-13)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.1.0...v1.1.1)

- Feature | Handle Saloon events by @pascalbaljet in #2 – Can now also log Requests/Responses when using Saloon's `MockClient` by handling `SendingSaloonRequest` and `SentSaloonRequest` Saloon events.
- Added common OAuth2 keys `access_token,refresh_token,client_secret` to default body obfuscation configuration.

## [v1.1.0 (2023-11-09)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.0.2...v1.1.0)

- Feature | HTTP Request Middleware to log Requests after Global Middleware by @pascalbaljet in #1

## [v1.0.2 (2023-07-17)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.0.1...v1.0.2)

- Drop Laravel 9 support
- PHP code style fixes by `laravel/pint` v1.10, now using more strict style rules (`laravel` preset).

## [v1.0.1 (2023-02-26)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.0.0...v1.0.1)

- Laravel 10 support, Require PHP 8.1
- Drop Laravel 8 / PHP 8.0 support
- Integrated `laravel/pint` as dev requirement for PHP style fixing

## [v1.0.0 (2022-02-10)](https://github.com/onlime/laravel-http-client-global-logger/compare/v0.9.1...v1.0.0)

- Laravel 9 support

## [v0.9.1 (2022-02-10)](https://github.com/onlime/laravel-http-client-global-logger/compare/v0.9...v0.9.1)

- Introducing `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_HEADERS` and `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_BODY_KEYS` env vars for easier configuration of request header/body obfuscation.
- Obfuscating request headers directly on PSR compliant request instance instead of applying regex replacements on formatted log message.

## [v0.9 (2021-07-12)](https://github.com/onlime/laravel-http-client-global-logger/releases/tag/v0.9)

- Initial release
