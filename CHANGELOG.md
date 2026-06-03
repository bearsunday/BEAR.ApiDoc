# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.11.0] - 2026-06-03

### Added
- Add `audit` output format and `apidoc audit` command for documentation coverage reports.
- Add `terms` output format and `apidoc terms` command for lexical Term Usage Index reports.
- Generate a `terms.html` companion Term Usage Index with HTML docs and `terms.md` with
  Markdown docs, linked automatically from `index.html` / `index.md` (#94).
- Render the HTML Term Usage Index as ALPS-profile-semantic markup: terms defined in ALPS
  carry their descriptor id as `class` and are flagged, descriptors are shown as labelled
  `title`/`def`/`doc` attributes, with an in-page Index for navigation (#94).

## [1.10.0] - 2026-05-17

### Added
- Reference fake-data JSON files as OpenAPI external examples with optional `<fakeData>` config (#85)
- Surface fake-data external example links in HTML and Markdown documentation (#85)

### Fixed
- Preserve JSON Schema param property definitions when expanding OpenAPI request bodies (#84)

## [1.9.1] - 2026-04-29

### Fixed
- Emit OpenAPI request inputs for `#[Input]` DTO methods without requiring `#[JsonSchema(params: ...)]` (#81)
- Fix `composer setup` to call the existing `apidoc init` command instead of a missing setup script (#86)
- Surface invalid JSON, XML loading, and output write failures during documentation generation (#86)

### Changed
- Allow `phpdocumentor/reflection-docblock` `^6.0` while keeping `^5.2` compatibility (#82)
- Emit non-path inputs for `POST`, `PUT`, and `PATCH` operations as JSON `requestBody` schemas instead of query parameters

## [1.9.0] - 2026-04-28

### Added
- Carry DTO property docblocks into OpenAPI parameter descriptions (#77, #78)
  - Promoted constructor property docblocks on `#[Input]` DTOs are surfaced as parameter descriptions
  - New `DescribedInputParam` decorator exposes the resolved description to renderers
  - Both summary and long description text are combined into the OpenAPI `description`
- Add `OptionsParam` and `ParamMeta` classes to support `OPTIONS` method documentation

### Changed
- Guard `DocBlockFactory::create()` against malformed docblocks so they are treated as undocumented instead of failing
- Pin `phpstan/phpstan` to `2.1.38` and `vimeo/psalm` to `7.0.0-beta14` in `vendor-bin/tools` for reproducible static analysis

### Fixed
- Restore PHP 8.2 compatibility by removing `#[\Override]` attributes and disabling Psalm's `ensureOverrideAttribute` check
- Fix `AbstractAppModule` constructor compatibility for lowest-deps installs (#75)
- Make `ThrowingDocBlockFactory::createInstance()` signature compatible across `phpdocumentor/reflection-docblock` 5.x and 6.x

## [1.8.0] - 2026-02-07

### Added
- Add `#[Input]` parameter expansion support for API documentation (#74)
  - Parameters annotated with `#[Input]` are expanded to their constructor parameters in generated docs
  - Applied consistently across HTML, Markdown, and OpenAPI generators
- Add ALPS semantic dictionary fallback for empty descriptions (#73) Thanks @jingu
  - When description is empty, ALPS profile `title`, `doc`, or `def` is used as fallback
  - Applied to request parameters and response properties in HTML output
- Add `ray/input-query` as direct dependency

### Fixed
- Fix XSD path in `ConfigGenerator` template: `vendor/bear/apidoc` → `vendor/bear/api-doc`

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

[Unreleased]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.11.0...HEAD
[1.11.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.10.0...1.11.0
[1.10.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.9.2...1.10.0
[1.9.2]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.9.1...1.9.2
[1.9.1]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.9.0...1.9.1
[1.9.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.8.0...1.9.0
[1.8.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.7.0...1.8.0
[1.7.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.6.0...1.7.0
[1.6.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.5.0...1.6.0
[1.5.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.4.0...1.5.0
[1.4.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.1.3...1.2.0
[1.1.3]: https://github.com/bearsunday/BEAR.ApiDoc/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/bearsunday/BEAR.ApiDoc/releases/tag/1.1.2
