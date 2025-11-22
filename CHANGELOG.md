# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2025-11-23

### Changed
- Remove `doctrine/annotations` dependency and use native PHP 8 attributes ([#51](https://github.com/bearsunday/BEAR.ApiDoc/pull/51))
- Migrate to native PHP 8 Reflection API for reading attributes
- Update all test fixtures to use PHP 8 attributes instead of annotations

### Fixed
- Fix test router configuration for bear/aura-router-module upgrade
- Remove conflicting 'bin' script from composer.json

### Removed
- `doctrine/annotations` dependency (package is abandoned)
- `Doctrine\Common\Annotations\Reader` usage throughout codebase

## [1.3.1] - 2025-02-28

### Changed
- Update CI configuration for PHP 8.4 ([#49](https://github.com/bearsunday/BEAR.ApiDoc/pull/49))
- Allow rize/uri-template v0.4 ([#50](https://github.com/bearsunday/BEAR.ApiDoc/pull/50))

## [1.3.0] - 2024-06-09

### Changed
- Stop supporting PHP 7.1 and minimum required version 8.1 ([#48](https://github.com/bearsunday/BEAR.ApiDoc/pull/48))

## [1.2.0] - 2024-01-11

### Changed
- Update dependencies

## [1.1.3] - 2023-07-07

### Changed
- Update dependencies

## [1.1.2] - 2022-03-01

### Changed
- Update dependencies

[Unreleased]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.4.0...HEAD
[1.4.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.1.3...1.2.0
[1.1.3]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/bearsunday/BEAR.ApiDoc/releases/tag/1.1.2
