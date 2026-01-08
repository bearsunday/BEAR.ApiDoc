# BEAR.ApiDoc

BEAR.ApiDoc generates API documentation from the application.

The documentation generated from the application's method signatures, phpdoc, JSON Schema, and ALPS profile will match the API documentation and the actual application.

This not only saves you the trouble of writing IDL, but also allows you to generate accurate documentation.

## Demo

[Live Demo](https://bearsunday.github.io/BEAR.ApiDoc/)

## Installation

    composer require bear/api-doc ^1.0

## Usage

See the [API doc documentatiom](http://bearsunday.github.io/manuals/1.0/en/apidoc.html).

## Development

```bash
git clone https://github.com/bearsunday/BEAR.ApiDoc.git
cd BEAR.ApiDoc
composer install
composer docs      # Generate docs with external CSS
composer docs-dev  # Generate docs with inline CSS for development
```
