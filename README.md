# BEAR.ApiDoc

## Installation

    composer require bear/api-doc --dev

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