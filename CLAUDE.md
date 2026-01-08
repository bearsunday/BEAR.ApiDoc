# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

BEAR.ApiDoc generates API documentation from BEAR.Sunday applications. It extracts documentation from method signatures, PHPDoc comments, and JSON Schema to produce accurate API documentation in HTML, Markdown, or OpenAPI 3.1 formats.

## Development Commands

```bash
# Run tests
composer test                              # Run PHPUnit tests
./vendor/bin/phpunit --filter <TestName>   # Run a single test

# Code quality
composer cs-fix                            # Fix coding standards (PHPCS)
composer sa                                # Static analysis (PHPStan + Psalm)
composer tests                             # Run cs, sa, and test together

# Generate API documentation (CLI usage)
./bin/apidoc                               # Uses apidoc.xml in current directory
./bin/apidoc -c path/to/apidoc.xml        # Specify config file
```

## Architecture

### Core Classes

- `ApiDoc` - Main entry point. Reads config, orchestrates documentation generation, outputs HTML/MD/OpenAPI
- `Config` - Parses `apidoc.xml` configuration, bootstraps the BEAR.Sunday application module to scan resources
- `DocClass` - Generates documentation for a resource class by examining its HTTP methods (onGet, onPut, etc.)
- `DocMethod` - Documents a single HTTP method with parameters and response schema
- `OpenApiGenerator` - Produces OpenAPI 3.1 specification from resource metadata

### Configuration

Configuration uses XML format validated against `apidoc.xsd`:

```xml
<apidoc>
    <appName>MyVendor\MyProject</appName>  <!-- Application namespace -->
    <scheme>app</scheme>                    <!-- Resource scheme: app or page -->
    <docDir>docs/api</docDir>              <!-- Output directory -->
    <format>html</format>                  <!-- html, md, or openapi -->
    <title>API Title</title>
    <alps>profile.json</alps>              <!-- Optional ALPS profile -->
</apidoc>
```

### Test Fixtures

- `tests/Fake/app/` - Complete fake BEAR.Sunday application for testing
- `tests/apidoc.*.xml` - Various test configurations (html, md, openapi, alps, etc.)
- `tests/docs/` - Generated documentation output from tests

### How Documentation is Generated

1. `Config` loads the target application's `AppModule` via Ray.Di
2. `Meta::getGenerator()` scans for resource classes in the specified scheme
3. For each resource, `DocClass` reflects on `onGet`, `onPut`, `onPost`, `onPatch`, `onDelete` methods
4. `DocMethod` extracts PHPDoc, parameters, and `@JsonSchema` annotations
5. JSON Schema files provide request/response validation and documentation
6. ALPS profiles (if configured) provide semantic definitions for parameters
