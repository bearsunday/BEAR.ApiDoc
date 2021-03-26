# /person

## GET

### Request
| Name  | Type  | Description | Default | Example |
|-------|-------|-------------|---------|---------| 
| id | string | The unique ID of the person. | koriym |  |
        

### Response
[Object: Person](schema/person.json)

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|------------|---------| 
| firstName | string | The person's first name. | Optional |  |  |
| lastName | string | The person's last name. | Optional |  |  |
| age | int | Age in years which must be equal to or greater than zero. | Optional | {"minimum":0} |  |
               