# /card

## GET

### Request
(No parameters required)

### Response
[Object: Card](schema/card.json)

| Name  | Type  | Description | Required | Constrain | Example |
|-------|-------|-------------|----------|-----------|---------| 
| fn | string | Formatted Name | Optional |  |  |
| familyName | string |  | Optional |  |  |
| givenName | string |  | Optional |  |  |
| additionalName | array |  | Optional | {"items":{"type":"string"}} |  |
| honorificPrefix | array |  | Optional | {"items":{"type":"string"}} |  |
| honorificSuffix | array |  | Optional | {"items":{"type":"string"}} |  |
| nickname | string |  | Optional |  |  |
| url | string |  | Optional |  |  |
| email | object |  | Optional | {"properties":{"type":{"type":"string"},"value":{"type":"string"}}} |  |
| tel | object |  | Optional | {"properties":{"type":{"type":"string"},"value":{"type":"string"}}} |  |
| adr | object |  | Optional | {"$ref":"[https:\/\/json-schema.org\/learn\/examples\/address.schema.json](https:\/\/json-schema.org\/learn\/examples\/address.schema.json)"} |  |
| geo | object |  | Optional | {"$ref":"[https:\/\/json-schema.org\/learn\/examples\/geographical-location.schema.json](https:\/\/json-schema.org\/learn\/examples\/geographical-location.schema.json)"} |  |
| tz | string |  | Optional |  |  |
| photo | string |  | Optional |  |  |
| logo | string |  | Optional |  |  |
| sound | string |  | Optional |  |  |
| bday | string |  | Optional |  |  |
| title | string |  | Optional |  |  |
| role | string |  | Optional |  |  |
| org | object |  | Optional | {"properties":{"organizationName":{"type":"string"},"organizationUnit":{"type":"string"}}} |  |
               