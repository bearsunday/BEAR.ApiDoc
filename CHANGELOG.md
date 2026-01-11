# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.0] - 2026-01-11

### Added
- Add `llms` format for AI-readable documentation (`llms.txt`) (#70)
- Support multiple output formats in single run with comma-separated values (#71)
  - Example: `<format>html,openapi,llms</format>`
- Default to `./apidoc.xml` when `-c` option is not specified

### Changed
- Update `apidoc.xml.dist` default format to `html,openapi,llms`
- Convert internal Generator to array for multiple format iteration

## [1.6.0] - 2026-01-09

### Added
- Add `apidoc init` command to generate `apidoc.xml` from `composer.json`
- Add helpful error message suggesting `apidoc init` when config not found
- Add backwards compatibility symlink `schema` -> `schemas`

## [1.5.0] - 2026-01-09

### Added
- Add ALPS semantic profiles for machine-readable documentation (#67)
  - `docs/alps/apidoc.xml` - Semantic definitions for all HTML elements
  - `docs/alps/index-schema.xml` - JSON Schema index semantics
  - `<link rel="profile">` in generated HTML for ALPS discovery
- Add `schemas/index.html` with `rel="schema"` links
- Add 📄 icon links to JSON Schema files in Objects section headings (#58)
- Nested objects (Card.Email, Card.Tel, etc.) link to parent schema file
- New documentation formats: Markdown and OpenAPI 3.1

### Changed
- Raise minimum PHP version from 8.1 to 8.2 (#57)
- Rename `schema/` directory to `schemas/` (JSON Schema convention)
- Array item types only link when target Object exists
- Update doctrine/coding-standard to ^13.0
- Update phpstan to ^2.0

### Breaking Changes
- `schema/` directory renamed to `schemas/`
- ApiDoc::__invoke() signature changed: removed $inlineCss parameter

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

[1.7.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.6.0...1.7.0
[1.6.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.5.0...1.6.0
[1.5.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.4.0...1.5.0
[1.4.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.1.3...1.2.0
[1.1.3]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/bearsunday/BEAR.ApiDoc/releases/tag/1.1.2
