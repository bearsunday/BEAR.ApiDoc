# BEAR.ApiDoc

BEAR.ApiDoc generates API documentation from the application.

The documentation generated from the application's method signatures, phpdoc, JSON Schema, and ALPS profile will match the API documentation and the actual application. It supports HTML, Markdown, and OpenAPI 3.1 formats.

This not only saves you the trouble of writing IDL, but also allows you to generate accurate documentation.

## Demo

- [ApiDoc](https://bearsunday.github.io/BEAR.ApiDoc/)
- [OpenAPI](https://bearsunday.github.io/BEAR.ApiDoc/openapi/)

## Installation

    requires PHP 8.2 or later

    composer require bear/api-doc ^1.0

## Usage

See the [API doc documentatiom](http://bearsunday.github.io/manuals/1.0/en/apidoc.html).

## GitHub Actions

You can use the reusable workflow to generate and publish API documentation automatically.

```yaml
name: API Docs
on:
  push:
    branches: [main]

jobs:
  docs:
    uses: bearsunday/BEAR.ApiDoc/.github/workflows/apidoc.yml@v1
    with:
      format: 'apidoc,openapi,alps'
      alps-profile: 'alps.json'
```

### Inputs

| Input | Default | Description |
|-------|---------|-------------|
| `php-version` | `'8.2'` | PHP version |
| `format` | `'apidoc'` | Comma-separated: apidoc, md, openapi, alps |
| `alps-profile` | `''` | ALPS profile path (required for alps format) |
| `docs-path` | `'docs/api'` | Output directory |
| `publish-to` | `'github-pages'` | `github-pages` or `artifact-only` |

### Output Structure

```text
docs/
├── index.html          # apidoc
├── schema/             # JSON Schema
│   └── *.json
├── openapi/
│   ├── openapi.json    # OpenAPI spec
│   └── index.html      # Redocly HTML
└── alps/
    ├── alps.json       # ALPS profile
    └── index.html      # ASD HTML
```

## Development

```bash
git clone https://github.com/bearsunday/BEAR.ApiDoc.git
cd BEAR.ApiDoc
composer install
composer docs        # Generate docs with external CSS
composer docs-dev    # Generate docs with inline CSS for development
composer docs-md     # Generate Markdown docs
composer docs-openapi # Generate OpenAPI spec
```
