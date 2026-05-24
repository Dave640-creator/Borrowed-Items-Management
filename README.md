# Borrow System

A simple borrowed items tracking system built with native PHP REST API and vanilla JavaScript.

## Requirements

- XAMPP (Apache + MySQL)
- Browser (Chrome recommended)

## Setup

1. Import `borrow_system.sql` in phpMyAdmin
2. Update your DB credentials in `config/database.php`
3. Copy the folder to `htdocs/`
4. Open `http://localhost/borrow_system/index.html`

## File Structure

```
borrow_system/
├── config/
│   ├── database.php      - database connection
│   └── helpers.php       - shared functions (response, validation, pagination)
├── api/
│   ├── get.php           - get all records
│   ├── get_one.php       - get single record
│   ├── create.php        - add new record
│   ├── update.php        - edit or mark as returned
│   ├── delete.php        - delete a record
│   └── filters.php       - get filter options
├── index.html            - main UI
├── script.js             - frontend logic
├── styles.css            - styles
└── borrow_system.sql     - database schema and sample data
```

## API Endpoints

### Get all records
```
GET api/get.php?filter=All&search=&page=1&limit=20
```

| Param | Default | Description |
|-------|---------|-------------|
| filter | All | All, Returned, Not Returned |
| search | - | search by item or borrower name |
| page | 1 | page number |
| limit | 20 | rows per page, max 100 |

### Get single record
```
GET api/get_one.php?id=1
```

### Add new record
```
POST api/create.php
```
```json
{
  "item_name": "HDMI Cable",
  "borrower_name": "Juan Dela Cruz",
  "phone_number": "09171234567",
  "borrow_date": "2026-05-24",
  "expected_return_date": "2026-05-31"
}
```

### Update a record
```
POST api/update.php
```
Mark as returned:
```json
{ "id": 1, "action": "mark_returned" }
```
Edit record:
```json
{
  "id": 1,
  "action": "edit",
  "borrower_name": "Maria Santos",
  "phone_number": "09181234567",
  "expected_return_date": "2026-06-01",
  "status": "Borrowed"
}
```

### Delete a record
```
POST api/delete.php
```
```json
{ "id": 1 }
```

## Validation Rules

- `item_name` - required, max 50 characters
- `borrower_name` - required, max 50 characters
- `phone_number` - optional, numbers only, max 20 characters
- `borrow_date` - required, format YYYY-MM-DD
- `expected_return_date` - required, must be after borrow date

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 400 | Bad Request |
| 404 | Not Found |
| 405 | Method Not Allowed |
| 422 | Validation Error |
| 500 | Server Error |
