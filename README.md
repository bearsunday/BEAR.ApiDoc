# BEAR.ApiDoc

Your application is the documentation.

- **ApiDoc HTML**: Developer documentation
- **OpenAPI 3.1**: Tool chain integration
- **JSON Schema**: Information model
- **ALPS**: Vocabulary semantics for AI understanding
- **llms.txt**: AI-readable application overview
- **Bindings HTML**: Ray.Di DI graph and binding provenance

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
- [Documentation Audit](https://bearsunday.github.io/BEAR.ApiDoc/audit.html)
- [Term Usage Index](https://bearsunday.github.io/BEAR.ApiDoc/terms.html)
- [Bindings](https://bearsunday.github.io/BEAR.ApiDoc/bindings.html)

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

This writes the API documentation and its term usage index for human-readable formats, for example `index.html` and `terms.html`.

Generate the documentation quality report:

```bash
./vendor/bin/apidoc audit
```

Each finding in the HTML audit carries a machine-readable `findingType`, so the report is a worklist, not just a list to read: the [`bear-audit-fix`](https://github.com/bearsunday/BEAR.Skills) skill reads `audit.html` and closes the gaps it reports (PHPDoc summaries, JSON Schema, `#[Alps]`).

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
      format: 'html,openapi,alps,audit,llms,bindings'
      alps-profile: 'alps.json'
```

### Inputs

| Input | Default | Description |
|-------|---------|-------------|
| `php-version` | `'8.2'` | PHP version |
| `format` | `'html,openapi,llms'` | Comma-separated: html (apidoc), md, openapi, alps, llms, audit, bindings |
| `alps-profile` | `''` | ALPS profile path (required for alps format) |
| `docs-path` | `'docs/api'` | Output directory |
| `publish-to` | `'github-pages'` | `github-pages` or `artifact-only` |

### Output Structure

```text
docs/
├── index.html          # API documentation
├── terms.html          # Term usage index for HTML docs
├── audit.html          # Documentation coverage report (HTML, ALPS-profiled)
├── audit.md            # Documentation coverage report (Markdown, for CLI/tooling)
├── bindings.html       # Ray.Di bindings and object graph
├── object-graph.dot    # Object graph source (DOT)
├── llms.txt            # AI-readable overview
├── openapi.json        # OpenAPI spec
├── alps/               # ALPS report profiles (terms.xml, audit.xml)
└── schemas/
    └── *.json          # JSON Schema
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
composer docs-audit  # Generate documentation audit report
composer docs-bindings # Generate Ray.Di bindings HTML report
composer docs-terms  # Generate legacy Markdown term usage index
```

Application as Documentation.
