# BEAR.ApiDoc

Your application is the documentation.

- **ApiDoc HTML**: Developer documentation
- **OpenAPI 3.1**: Tool chain integration
- **JSON Schema**: Information model
- **ALPS**: Vocabulary semantics for AI understanding
- **llms.txt**: AI-readable application overview

## Semantic Depth

The same document reveals different insights depending on your perspective:

| Depth | What You See |
|-------|--------------|
| Surface | Remote function list (API reference) |
| Middle | Resources and operations (REST design) |
| Deep | Application semantics (ALPS three layers) |

A developer sees endpoints to call. An architect sees state transitions. An AI extracts Ontology, Taxonomy, and Choreography. One document, multiple layers of understanding.

This is a semantic application document, not just an API reference. It describes what the application **is**, not just how to call it.

## Demo

- [ApiDoc](https://bearsunday.github.io/BEAR.ApiDoc/)
- [OpenAPI](https://bearsunday.github.io/BEAR.ApiDoc/openapi/)

## Installation

    composer require bear/api-doc ^1.0

## Quick Start

Generate configuration file:

```bash
./vendor/bin/apidoc init
```

This creates `apidoc.xml` from your `composer.json`.

Generate documentation:

```bash
./vendor/bin/apidoc
```

## Usage

See the [API doc documentation](http://bearsunday.github.io/manuals/1.0/en/apidoc.html).

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
      format: 'html,openapi,alps'
      alps-profile: 'alps.json'
```

### Inputs

| Input | Default | Description |
|-------|---------|-------------|
| `php-version` | `'8.2'` | PHP version |
| `format` | `'html,openapi'` | Comma-separated: html (apidoc), md, openapi, alps, llms |
| `alps-profile` | `''` | ALPS profile path (required for alps format) |
| `docs-path` | `'docs/api'` | Output directory |
| `publish-to` | `'github-pages'` | `github-pages` or `artifact-only` |

### Output Structure

```text
docs/
├── index.html          # apidoc
├── llms.txt            # AI-readable overview
├── schemas/            # JSON Schema
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

Application as Documentation.
