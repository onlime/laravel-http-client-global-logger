# CHANGELOG

## [v1.1.x (Unreleased)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.1.1...main)


## [v1.1.1 (2023-11-13)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.1.0...v1.1.1)

- Feature | Handle Saloon events by @pascalbaljet in #2 – Can now also log Requests/Responses when using Saloon's `MockClient` by handling `SendingSaloonRequest` and `SentSaloonRequest` Saloon events.
- Added common OAuth2 keys `access_token,refresh_token,client_secret` to default body obfuscation configuration.

## [v1.1.0 (2023-11-09)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.0.2...v1.1.0)

- Feature | HTTP Request Middleware to log Requests after Global Middleware by @pascalbaljet in #1

## [v1.0.2 (2023-07-17)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.0.1...v1.0.2)

- Drop Laravel 9 support
- PHP code style fixes by `laravel/pint` v1.10, now using more strict style rules (`laravel` preset).

## [v1.0.1 (2023-02-26)](https://github.com/onlime/laravel-http-client-global-logger/compare/v1.0.0...v1.0.1)

- Drop Laravel 8 / PHP 8.0 support
- Integrated `laravel/pint` as dev requirement for PHP style fixing
- Support Laravel 10

## [v1.0.0 (2022-02-10)](https://github.com/onlime/laravel-http-client-global-logger/compare/v0.9.1...v1.0.0)

- Support Laravel 9

## [v0.9.1 (2022-02-10)](https://github.com/onlime/laravel-http-client-global-logger/compare/v0.9...v0.9.1)

- Introducing `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_HEADERS` and `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_BODY_KEYS` env vars for easier configuration of request header/body obfuscation.
- Obfuscating request headers directly on PSR compliant request instance instead of applying regex replacements on formatted log message.

## [v0.9 (2021-07-12)](https://github.com/onlime/laravel-http-client-global-logger/releases/tag/v0.9)

- Initial release
