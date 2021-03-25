# /users/{id}

## GET

### Request
| Name  | Type  | Description | Default | Example |
|-------|-------|-------------|---------|---------| 
| id | string | User ID |  |  |
| options | string | User Options | guest |  |
        

### Response
[Object: User](schema/user.json)

| Name  | Type  | Description | Required | Constrain | Example |
|-------|-------|-------------|----------|-----------|---------| 
| id | string | The unique ID of the user | Optional | {"maxLength":30} |  |
| firstName | string | The first name of the user | Optional | {"maxLength":30,"pattern":"[a-z\\d~+-]+"} |  |
| lastName | string | The last name of the user | Optional | {"maxLength":30,"pattern":"[a-z\\d~+-]+"} |  |
| created | string | When the user record was created | Optional | {"format":"date-time"} | 2018-01-01T12:00:00Z |
| modified | string | When the user record was last modified | Optional | {"format":"date-time"} | 2018-01-01T12:00:00Z |
| email | string | The email address of the user | Optional | {"format":"email"} |  |
| enabled | boolean | Whether the user is enabled or not | Optional |  |  |
| age | int | The age of the user | Optional | {"$ref":"[age.json](schema\/age.json)"} | 29 |
               
## POST

### Request
| Name  | Type  | Description | Default | Example |
|-------|-------|-------------|---------|---------| 
| name | string | The name of the user |  |  |
| age | int | The age of the user |  |  |
        

### Response
(No response body)       