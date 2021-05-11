# /person

## GET


**Request**

| Name  | Type  | Description | Default | Required | Constraints | Example |
|-------|-------|-------------|---------|----------|-------------|---------| 
| id | string | The unique ID of the person. | koriym | Optional |  |  


**Response**

[Object: Person](schema/person.json)

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|------------|---------| 
| firstName | string | 名前 | Optional |  |  |
| familyName | string | [https://schema.org/familyName](https://schema.org/familyName) | Optional |  |  |
| age | int | Age in years which must be equal to or greater than zero. | Optional | {"minimum":0} |  |

#### Embedded

| rel | src |
|-----|-----|
| org | [<code>/org?id={org_id}</code>](org.md) |

#### Links

| rel | href |
|-----|-----|
| card | [<code>/card?id={card_id}</code>](card.md) |
| tickets | [<code>/tickets</code>](tickets.md) |