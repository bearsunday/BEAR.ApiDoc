# Term Usage Index

This index reports lexical identifier matches only; it does not prove semantic equivalence.

## Summary

- Terms used in API: 73
- Terms with same-name ALPS descriptor: 4
- Lexical ALPS coverage: 5.5%
- Reserved representation fields: 2
- ☑︎ = ALPS descriptor binding

## Terms

### `additionalName`

- usages:
  - schema property: card.json#/properties/additionalName

### `age` ☑︎

- title: Age in years which must be equal to or greater than zero.
- usages:
  - parameter: GET /alps-fallback {age}
  - parameter: POST /contact {age}
  - parameter: POST /contact-with-descriptions {age}
  - parameter: POST /person {age}
  - parameter: PATCH /person {age}
  - parameter: POST /users/{id} {age}
  - parameter: PUT /users/{id} {age}
  - schema property: contact.param.json#/properties/age
  - schema property: person.json#/properties/age
  - schema property: person.param.json#/properties/age
  - schema property: user.json#/properties/age
  - schema property: user.param.json#/properties/age

### `assignee`

- usages:
  - parameter: POST /ticket/{id} {assignee}
  - parameter: PUT /ticket/{id} {assignee}
  - parameter: PUT /ticket/assign {assignee}
  - schema property: ticket.json#/properties/assignee
  - schema property: ticket.param.json#/properties/assignee

### `bday`

- usages:
  - schema property: card.json#/properties/bday

### `bothSummaryAndDescription`

- usages:
  - parameter: POST /docblock-variants {bothSummaryAndDescription}

### `builtinType`

- usages:
  - parameter: POST /input-edge-cases {builtinType}

### `category`

- usages:
  - schema property: calendar.json#/properties/category

### `count`

- usages:
  - parameter: GET /numbers {count}

### `country-name`

- usages:
  - schema property: address.json#/properties/country-name

### `created`

- usages:
  - schema property: ticket.json#/properties/created
  - schema property: user.json#/properties/created

### `date`

- usages:
  - parameter: GET /calendar/{year}/{month} {date}

### `description`

- usages:
  - parameter: POST /ticket/{id} {description}
  - parameter: PUT /ticket/{id} {description}
  - schema property: calendar.json#/properties/description
  - schema property: ticket.json#/properties/description
  - schema property: ticket.param.json#/properties/description

### `docOnly`

- usages:
  - schema property: json-schema-input.param.json#/properties/docOnly

### `dtend`

- usages:
  - schema property: calendar.json#/properties/dtend

### `dtstart`

- usages:
  - schema property: calendar.json#/properties/dtstart

### `duration`

- usages:
  - schema property: calendar.json#/properties/duration

### `email`

- usages:
  - parameter: POST /contact {email}
  - parameter: POST /contact-with-descriptions {email}
  - parameter: POST /users/{id} {email}
  - parameter: PUT /users/{id} {email}
  - schema property: card.json#/properties/email
  - schema property: contact.param.json#/properties/email
  - schema property: user.json#/properties/email
  - schema property: user.param.json#/properties/email

### `enabled`

- usages:
  - parameter: PUT /users/{id} {enabled}
  - schema property: user.json#/properties/enabled
  - schema property: user.param.json#/properties/enabled

### `extended-address`

- usages:
  - schema property: address.json#/properties/extended-address

### `familyName` ☑︎

- def: https://schema.org/familyName
- usages:
  - parameter: GET /alps-fallback {familyName}
  - parameter: POST /person {familyName}
  - parameter: PATCH /person {familyName}
  - schema property: card.json#/properties/familyName
  - schema property: person.json#/properties/familyName
  - schema property: person.param.json#/properties/familyName

### `firstName` ☑︎

- title: First Name by ALPS
- usages:
  - parameter: GET /alps-fallback {firstName}
  - parameter: POST /person {firstName}
  - parameter: PATCH /person {firstName}
  - schema property: person.json#/properties/firstName
  - schema property: person.param.json#/properties/firstName
  - schema property: user.json#/properties/firstName

### `fn`

- usages:
  - schema property: card.json#/properties/fn

### `foo`

- usages:
  - parameter: GET /alps-fallback {foo}

### `fruits`

- usages:
  - schema property: array.json#/properties/fruits

### `givenName`

- usages:
  - schema property: card.json#/properties/givenName

### `honorificPrefix`

- usages:
  - schema property: card.json#/properties/honorificPrefix

### `honorificSuffix`

- usages:
  - schema property: card.json#/properties/honorificSuffix

### `href`

- usages:
  - schema property: user.json#/properties/_links/properties/self/properties/href

### `id` ☑︎

- title: Person identifier
- usages:
  - parameter: GET /address {id}
  - parameter: GET /card {id}
  - parameter: GET /org {id}
  - parameter: GET /person {id}
  - parameter: PATCH /person {id}
  - parameter: GET /ticket/{id} {id}
  - parameter: PUT /ticket/{id} {id}
  - parameter: DELETE /ticket/{id} {id}
  - parameter: PUT /ticket/assign {id}
  - parameter: GET /union-type {id}
  - parameter: GET /users/{id} {id}
  - parameter: PUT /users/{id} {id}
  - parameter: DELETE /users/{id} {id}
  - schema property: org.json#/properties/id
  - schema property: person.param.json#/properties/id
  - schema property: ticket.json#/properties/id
  - schema property: ticket.param.json#/properties/id
  - schema property: user.json#/properties/id
  - schema property: user.param.json#/properties/id

### `ids`

- usages:
  - schema property: json-schema-input.param.json#/properties/ids

### `items`

- usages:
  - parameter: GET /array-data {items}

### `juice`

- usages:
  - schema property: array.json#/properties/juice

### `juiceLike`

- usages:
  - schema property: array.json#/definitions/juice/properties/juiceLike

### `juiceName`

- usages:
  - schema property: array.json#/definitions/juice/properties/juiceName

### `lastName`

- usages:
  - schema property: user.json#/properties/lastName

### `locality`

- usages:
  - schema property: address.json#/properties/locality

### `location`

- usages:
  - schema property: calendar.json#/properties/location

### `logo`

- usages:
  - schema property: card.json#/properties/logo

### `modified`

- usages:
  - schema property: user.json#/properties/modified

### `name`

- usages:
  - parameter: POST /contact {name}
  - parameter: POST /contact-with-descriptions {name}
  - parameter: POST /users/{id} {name}
  - parameter: PUT /users/{id} {name}
  - schema property: contact.param.json#/properties/name
  - schema property: org.json#/properties/name
  - schema property: user.param.json#/properties/name

### `nickname`

- usages:
  - schema property: card.json#/properties/nickname

### `noCtor`

- usages:
  - parameter: PUT /input-edge-cases {noCtor}

### `nonPromoted`

- usages:
  - parameter: POST /docblock-variants {nonPromoted}

### `options`

- usages:
  - parameter: GET /users/{id} {options}
  - schema property: user.param.json#/properties/options

### `org`

- usages:
  - schema property: card.json#/properties/org

### `organizationName`

- usages:
  - schema property: card.json#/properties/org/properties/organizationName

### `organizationUnit`

- usages:
  - schema property: card.json#/properties/org/properties/organizationUnit

### `photo`

- usages:
  - schema property: card.json#/properties/photo

### `post-office-box`

- usages:
  - schema property: address.json#/properties/post-office-box

### `postal-code`

- usages:
  - schema property: address.json#/properties/postal-code

### `priority`

- usages:
  - schema property: ticket.json#/properties/priority

### `rdate`

- usages:
  - schema property: calendar.json#/properties/rdate

### `region`

- usages:
  - schema property: address.json#/properties/region

### `role`

- usages:
  - schema property: card.json#/properties/role

### `rrule`

- usages:
  - schema property: calendar.json#/properties/rrule

### `self`

- usages:
  - schema property: user.json#/properties/_links/properties/self

### `sound`

- usages:
  - schema property: card.json#/properties/sound

### `status`

- usages:
  - parameter: PUT /ticket/{id} {status}
  - schema property: json-schema-input.param.json#/properties/status
  - schema property: ticket.json#/properties/status
  - schema property: ticket.param.json#/properties/status

### `street-address`

- usages:
  - schema property: address.json#/properties/street-address

### `subject`

- usages:
  - parameter: POST /contact {subject}
  - parameter: POST /contact-with-descriptions {subject}
  - schema property: contact.param.json#/properties/subject

### `summary`

- usages:
  - schema property: calendar.json#/properties/summary

### `tagOnly`

- usages:
  - parameter: POST /docblock-variants {tagOnly}

### `tel`

- usages:
  - schema property: card.json#/properties/tel

### `tickets`

- usages:
  - schema property: user.json#/properties/_embedded/properties/tickets

### `title`

- usages:
  - parameter: POST /ticket/{id} {title}
  - parameter: PUT /ticket/{id} {title}
  - schema property: card.json#/properties/title
  - schema property: ticket.json#/properties/title
  - schema property: ticket.param.json#/properties/title

### `type`

- usages:
  - schema property: card.json#/properties/email/properties/type
  - schema property: card.json#/properties/tel/properties/type

### `tz`

- usages:
  - schema property: card.json#/properties/tz

### `updated`

- usages:
  - schema property: ticket.json#/properties/updated

### `url`

- usages:
  - schema property: calendar.json#/properties/url
  - schema property: card.json#/properties/url

### `value`

- usages:
  - schema property: card.json#/properties/email/properties/value
  - schema property: card.json#/properties/tel/properties/value

### `vegetables`

- usages:
  - schema property: array.json#/properties/vegetables

### `veggieLike`

- usages:
  - schema property: array.json#/definitions/veggie/properties/veggieLike

### `veggieName`

- usages:
  - schema property: array.json#/definitions/veggie/properties/veggieName

## Reserved Representation Fields

Leading-underscore fields are listed separately because they usually belong to the representation format rather than the API domain vocabulary.

### Field: `_embedded`

- usages:
  - schema property: user.json#/properties/_embedded

### Field: `_links`

- usages:
  - schema property: user.json#/properties/_links
