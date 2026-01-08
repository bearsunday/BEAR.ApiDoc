# BEAR.ApiDoc

BEAR.ApiDoc visualizes your API design and publishes it in formats that both humans and machines can understand.

- **HTML**: Developer documentation
- **OpenAPI 3.1**: Tool chain integration (SDK generation, mock servers, Swagger UI)
- **JSON Schema**: Client-side validation and form generation
- **ALPS**: Semantic vocabulary definitions

The documentation generated from your code and JSON Schema is always accurate and synchronized with the actual implementation.

## Demo

- [ApiDoc](https://bearsunday.github.io/BEAR.ApiDoc/)
- [OpenAPI](https://bearsunday.github.io/BEAR.ApiDoc/openapi/)

## Installation

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
      format: 'html,openapi,alps'
      alps-profile: 'alps.json'
```

### Inputs

| Input | Default | Description |
|-------|---------|-------------|
| `php-version` | `'8.2'` | PHP version |
| `format` | `'html,openapi'` | Comma-separated: html (apidoc), md, openapi, alps |
| `alps-profile` | `''` | ALPS profile path (required for alps format) |
| `docs-path` | `'docs/api'` | Output directory |
| `publish-to` | `'github-pages'` | `github-pages` or `artifact-only` |

### Output Structure

```text
docs/
├── index.html          # apidoc
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
