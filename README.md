# BEAR.ApiDoc

BEAR.ApiDoc generates API documentation from the application.

The documentation generated from the application's method signatures, phpdoc, and JSON Shcema will match the API documentation and the actual application.

This not only saves you the trouble of writing IDL, but also allows you to generate accurate documentation.


## Installation

    composer require bear/api-doc 1.x-dev --dev

## Usage

Basic usage:

```php
(new DocApp('FakeVendor\FakeProject'))('/path/to/docs', 'app');
```

Instead of adding explanations for each of them, the ALPS profile can be used to centralize the explanation of terms. In other words, it is a dictionary of terms used in the application.


```php
(new DocApp('FakeVendor\FakeProject'))('/path/to/docs', 'app', '/path/to/profile.json');
```

### ALPS file

Use `title` to describe the term, or `def` to link to a URI where the meaning of the term is defined.

```json
{
  "$schema": "https://alps-io.github.io/schemas/alps.json",
  "alps": {
    "descriptor": [
      {"id": "firstName", "title": "The person's first name."},
      {"id": "familyName", "def": "https://schema.org/familyName"},
    ]
  }
}
```

For more information about ALPS, please visit [alps.io](http://alps.io/)

## Demo

Try the online APIdoc [demo](https://github.com/bearsunday/BEAR.ApiDoc/blob/1.x/tests/docs/index.md).
