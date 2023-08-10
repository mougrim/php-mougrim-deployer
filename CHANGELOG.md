# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v0.4.0]

### Changed
- Use LoggerInterface ([#2](https://github.com/mougrim/php-mougrim-deployer/pull/2))
- Extract TemplateHelper::processTemplateToFile() to TemplateShellHelper ([#3](https://github.com/mougrim/php-mougrim-deployer/pull/3))

### Removed
- Stop creating links from application path to version files and folders ([#4](https://github.com/mougrim/php-mougrim-deployer/pull/4))

## [v0.3.0]

### Changed
- Up php to 8.1 ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Change mougrim/php-logger to own psr/log compatible logger ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Add type declaring and hints everywhere ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Add declare(strict_types=1) to all php-files ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Pass parameters to constructor instead of using setters ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Use psr-4 autoload instead of psr-0 ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Use readonly properties instead of getters ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Add badges to README.md ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))
- Chane placeholders in param values using param values ([#1](https://github.com/mougrim/php-mougrim-deployer/pull/1))

[unreleased]: https://github.com/mougrim/php-mougrim-deployer/compare/v0.4.0...HEAD
[v0.4.0]: https://github.com/mougrim/php-mougrim-deployer/compare/v0.3.0...v0.4.0
[v0.3.0]: https://github.com/mougrim/php-mougrim-deployer/compare/v0.2.2...v0.3.0
