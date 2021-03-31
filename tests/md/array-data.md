# /array-data

## GET


### Request
(No parameters required)

### Response
[Object: Array](schema/array.json)

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|------------|---------| 
| fruits | array |  | Optional | {"items":{"type":"string"}} |  |
| vegetables | array |  | Optional | {"items":{"$ref":"#\/definitions\/veggie"}} |  |
| juice | object |  | Optional | {"$ref":"#\/definitions\/juice"} |  |
