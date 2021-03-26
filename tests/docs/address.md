# /address
Address

This is the summary of Address. line 1
This is the summary of Address. line 2

 * @link [http://www.example.com/1](http://www.example.com/1) Link description 1
 * @link [http://www.example.com/2](http://www.example.com/2) Link description 2


## GET

### Request
(No parameters required)

### Response
[Object: Address](schema/address.json)

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|------------|---------| 
| post-office-box | string | Post Office Box - where a person or business can have mail delivered. | Optional |  |  |
| extended-address | string |  | Optional |  |  |
| street-address | string |  | Optional |  |  |
| locality | string |  | Optional |  |  |
| region | int&#124;string |  | Optional |  |  |
| postal-code | string |  | Optional |  |  |
| country-name | string |  | Optional |  |  |
               