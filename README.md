# Borrow System – REST API

Refactored native PHP REST API.  
Each endpoint lives in its own file under `/api/`.

---

## File Structure

```
borrow_api/
├── config/
│   ├── database.php   ← DB connection
│   └── helpers.php    ← send_response, sanitize, validate_string, pagination …
├── api/
│   ├── get.php        ← GET all records (paginated, filterable, searchable)
│   ├── get_one.php    ← GET single record by id
│   ├── create.php     ← POST create new record
│   ├── update.php     ← POST edit or mark returned
│   ├── delete.php     ← POST delete record
│   └── filters.php    ← GET available filter options
└── borrow_system.sql  ← Database schema + sample data
```

---

## Endpoints

### GET /api/get.php
Retrieve records with optional filtering, search, and **pagination**.

| Param    | Type   | Default      | Notes                              |
|----------|--------|--------------|------------------------------------|
| filter   | string | `All`        | `All`, `Returned`, `Not Returned`  |
| search   | string | _(empty)_    | Matches `item_name`, `borrower_name` |
| page     | int    | `1`          | Page number                        |
| limit    | int    | `20`         | Rows per page (max 100)            |

**Success response:**
```json
{
  "success": true,
  "message": "Records retrieved successfully.",
  "data": [ { "id": 1, "item_name": "HDMI Cable", "display_status": "Borrowed", ... } ],
  "meta": {
    "total_records": 42,
    "total_pages": 3,
    "current_page": 1,
    "limit": 20
  }
}
```

**Empty response (no records found):**
```json
{
  "success": true,
  "message": "No records found.",
  "data": [],
  "meta": { "total_records": 0, "total_pages": 0, "current_page": 1, "limit": 20 }
}
```

---

### GET /api/get_one.php?id=5
Get a single record by ID.

---

### POST /api/create.php
Create a new borrow record.

**Request body:**
```json
{
  "item_name"            : "HDMI Cable",
  "borrower_name"        : "Juan Dela Cruz",
  "borrow_date"          : "2026-05-24",
  "expected_return_date" : "2026-05-31"
}
```

**Validation rules:**
- `item_name` – required, max **50 characters**
- `borrower_name` – required, max **50 characters**
- `borrow_date` – required, format `YYYY-MM-DD`
- `expected_return_date` – required, must be **after** `borrow_date`

**Validation error response (HTTP 422):**
```json
{
  "success": false,
  "message": "Validation failed.",
  "data": {
    "errors": {
      "item_name": "Item name must not exceed 50 characters.",
      "borrower_name": "Borrower name is required."
    }
  }
}
```

---

### POST /api/update.php
Edit a record or mark it returned.

**Mark returned:**
```json
{ "id": 5, "action": "mark_returned" }
```

**Edit:**
```json
{
  "id"                   : 5,
  "action"               : "edit",
  "borrower_name"        : "Maria Santos",
  "expected_return_date" : "2026-06-01",
  "status"               : "Borrowed"
}
```

---

### POST /api/delete.php
Delete a record.

```json
{ "id": 5 }
```

---

### GET /api/filters.php
Returns available filter options.

---

## HTTP Status Codes Used

| Code | Meaning               |
|------|-----------------------|
| 200  | OK                    |
| 201  | Created               |
| 204  | No Content (OPTIONS)  |
| 400  | Bad Request           |
| 404  | Not Found             |
| 405  | Method Not Allowed    |
| 422  | Validation Error      |
| 500  | Server Error          |
