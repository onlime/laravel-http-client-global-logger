# CHANGELOG

## [1.x (Unreleased)](https://github.com/onlime/laravel-http-client-global-logger/compare/1.0...main)

## [1.0.0 (2022-02-10)](https://github.com/onlime/laravel-http-client-global-logger/releases/tag/1.0.0)

- Support Laravel 9

## [v0.9.1 (2022-02-10)](https://github.com/onlime/laravel-http-client-global-logger/releases/tag/v0.9.1)

- Introducing `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_HEADERS` and `HTTP_CLIENT_GLOBAL_LOGGER_OBFUSCATE_BODY_KEYS` env vars for easier configuration of request header/body obfuscation.
- Obfuscating request headers directly on PSR compliant request instance instead of applying regex replacements on formatted log message.

## [v0.9 (2021-07-12)](https://github.com/onlime/laravel-http-client-global-logger/releases/tag/v0.9)

- Initial release
