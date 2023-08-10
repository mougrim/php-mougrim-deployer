# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Use LoggerInterface

## [v0.3.0]

### Changed
- Up php to 8.1
- Change mougrim/php-logger to own psr/log compatible logger
- Add type declaring and hints everywhere
- Add declare(strict_types=1) to all php-files
- Pass parameters to constructor instead of using setters
- Use psr-4 autoload instead of psr-0
- Use readonly properties instead of getters
- Add badges to README.md
- Chane placeholders in param values using param values

[unreleased]: https://github.com/mougrim/php-mougrim-deployer/compare/v0.3.0...HEAD
[v0.3.0]: https://github.com/mougrim/php-mougrim-deployer/compare/v0.2.2...v0.3.0
