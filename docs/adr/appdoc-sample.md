# MyVendor\MyApp

A ticket management system with user authentication.

## Routes (3)

| HTTP Route | Methods | Resource |
|------------|---------|----------|
| /api/users/{id} | GET, POST, PUT, DELETE | App/Users |
| /api/tickets/{id} | GET, POST, PUT, DELETE | App/Ticket |
| /api/tickets/assign | POST | App/Ticket/Assign |

## ResourceObjects (3)

### App/Users
`/users/{id}`

| Method | Parameters | Returns | req-schema | res-schema | Links |
|--------|------------|---------|------------|------------|-------|
| GET | id*, options | User | - | user.json | href(goPerson, goCalendar), src(ticket) |
| POST | name*, age*, email | - | user-create.json | - | - |
| PUT | id*, name, age | - | user-update.json | - | - |
| DELETE | id* | - | - | - | - |

### App/Ticket
`/ticket/{id}`

| Method | Parameters | Returns | req-schema | res-schema | Links |
|--------|------------|---------|------------|------------|-------|
| GET | id* | Ticket | - | ticket.json | href(goUser) |
| POST | title*, description*, assignee | - | ticket-create.json | - | - |
| PUT | id*, title, status | - | ticket-update.json | - | - |
| DELETE | id* | - | - | - | - |

### App/Ticket/Assign
`/ticket/assign`

| Method | Parameters | Returns | req-schema | res-schema | Links |
|--------|------------|---------|------------|------------|-------|
| POST | ticketId*, userId* | - | ticket-assign.json | - | - |

## Query Interfaces (4)

| Interface | Methods |
|-----------|---------|
| UserQueryInterface | getUser(id):User, getUsers(limit):array |
| UserCommandInterface | create(id, name, age, email):void, update(id, name, age):void, delete(id):void |
| TicketQueryInterface | getTicket(id):Ticket, getTickets(limit):array |
| TicketCommandInterface | create(id, title, description, assignee):void, assign(ticketId, userId):void |

## SQL (5)

| File | Query |
|------|-------|
| user_item.sql | `SELECT id, name, email, age, created FROM users WHERE id = :id` |
| user_list.sql | `SELECT id, name, email FROM users ORDER BY created DESC LIMIT :limit` |
| ticket_item.sql | `SELECT id, title, description, status, assignee FROM tickets WHERE id = :id` |
| ticket_add.sql | `INSERT INTO tickets (id, title, ...) VALUES (:id, :title, ...)` |
| ticket_assign.sql | `UPDATE tickets SET assignee = :userId WHERE id = :ticketId` |

## Entities (2)

| Entity | Properties |
|--------|------------|
| User | id, name, email, age, created |
| Ticket | id, title, description, status, assignee, created |
