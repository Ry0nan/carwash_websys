# Carwash Management System — Backend API

A small Laravel 11 REST API for a **Web-Based Carwash Customer Account and Job Order Management System**.

If you just want to get this running locally, you really only need the four commands in the next section.

---

## What you need

See `requirements.txt` for the full list, but in short:

- **PHP 8.2+**
- **Composer**
- **MySQL 8.0+**

Make sure you can run `php -v`, `composer -V`, and that you have a MySQL database user ready.

---

## Quick Start (4 steps)

All commands assume you are in your `Downloads` folder on Windows.

### 1. Create the Laravel project and drop these files in

```bash
cd %USERPROFILE%\Downloads
composer create-project laravel/laravel carwash-api
cd carwash-api
```

Now copy/unzip this repo’s contents **into** the new `carwash-api` folder, letting it overwrite where needed.

### 2. Configure your `.env`

```bash
copy .env.example .env
php artisan key:generate
```

Then open `.env` in your editor and set the database pieces:

- `DB_DATABASE=carwash_db`
- `DB_USERNAME=your_mysql_user`
- `DB_PASSWORD=your_mysql_password`

Create the database if it doesn’t exist yet:

```sql
CREATE DATABASE carwash_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Run migrations and seed data

From the project root:

```bash
php artisan migrate
php artisan db:seed
```

That will:
- create all tables, and
- insert an admin user and all the default services/pricing.

Default admin (you can change this in `database/seeders/AdminSeeder.php`):

| Field    | Value               |
|----------|---------------------|
| Email    | admin@carwash.local |
| Password | Admin@1234          |

### 4. Start the API server

```bash
php artisan serve
```

The API will be available at: `http://127.0.0.1:8000/api`

You can now log in from your frontend or API client using the admin account above.

---

## Project Structure

```
app/
  Http/
    Controllers/Api/
      AuthController.php        — Login, logout, me
      CustomerController.php    — Customer CRUD + search
      VehicleController.php     — Vehicle CRUD + search
      ServiceController.php     — Service/pricing management + quote preview
      JobOrderController.php    — Job order lifecycle + items
      ReportController.php      — Daily report
    Middleware/
      AdminAuth.php             — Session guard middleware
  Models/
    User.php
    Customer.php
    Vehicle.php
    Service.php
    ServicePricing.php
    JobOrder.php
    JobOrderItem.php
  Services/
    PricingService.php          — Resolves prices from service_pricing table
    JobOrderService.php         — Validates service selection rules, builds items

database/
  migrations/                   — 7 migration files (in order)
  seeders/
    AdminSeeder.php             — Default admin user
    ServiceSeeder.php           — All services + pricing data
    DatabaseSeeder.php

routes/
  api.php                       — All API routes
```

---

## API Reference

All endpoints return JSON. All protected endpoints require an active admin session cookie.

### Authentication

#### `POST /api/auth/login`
```json
{
  "email": "admin@carwash.local",
  "password": "Admin@1234"
}
```
**Response:** Sets a session cookie.

---

#### `POST /api/auth/logout`
Clears the session. Requires auth.

---

#### `GET /api/auth/me`
Returns the current logged-in admin.

---

### Customers

#### `GET /api/customers?search=<query>`
Search by name, contact number, or plate number. Returns paginated results.

#### `POST /api/customers`
```json
{ "full_name": "Juan dela Cruz", "contact_number": "+639171234567" }
```

#### `GET /api/customers/:id`
#### `PUT /api/customers/:id`
```json
{ "full_name": "Juan dela Cruz Jr." }
```

#### `DELETE /api/customers/:id`
Returns `409 Conflict` if customer has vehicles or job orders.

---

### Vehicles

#### `GET /api/vehicles?search=<query>`
Search by plate number, customer name, or contact number.

#### `POST /api/vehicles`
```json
{
  "customer_id": 1,
  "plate_number": "ABC1234",
  "vehicle_category": "CAR",
  "vehicle_size": "MEDIUM"
}
```
- `vehicle_category`: `CAR` or `MOTOR`
- `vehicle_size`: `SMALL`, `MEDIUM`, `LARGE`, or `XL` — **required for CAR, ignored for MOTOR**

#### `GET /api/vehicles/:id`
#### `PUT /api/vehicles/:id`
#### `DELETE /api/vehicles/:id`
Returns `409 Conflict` if vehicle has job orders.

---

### Services & Pricing

#### `GET /api/services?vehicle_category=CAR&active=1`
Returns all services with their pricing rows.

#### `POST /api/services`
```json
{
  "service_name": "Detailing",
  "vehicle_category": "BOTH",
  "service_group": "OTHER",
  "is_active": true
}
```
- `vehicle_category`: `CAR`, `MOTOR`, `BOTH`
- `service_group`: `PACKAGE`, `ADDON`, `BUNDLE`, `MOTOR_MAIN`, `OTHER`

#### `PUT /api/services/:id`
Same fields as POST, all optional.

#### `GET /api/services/:id/pricing`
Returns all pricing rows for this service.

#### `PUT /api/services/:id/pricing`
Bulk upsert pricing:
```json
{
  "pricing": [
    { "vehicle_size": "SMALL",  "price": 180 },
    { "vehicle_size": "MEDIUM", "price": 200 },
    { "vehicle_size": "LARGE",  "price": 220 },
    { "vehicle_size": "XL",     "price": 250 }
  ]
}
```
For motor fixed-price service: `{ "vehicle_size": null, "price": 100 }`

#### `GET /api/pricing/quote-preview?vehicle_id=1&service_ids[]=2&service_ids[]=3`
Returns resolved prices for selected services based on vehicle size.

---

### Job Orders

#### `POST /api/job-orders`
Create a new job order (tablet check-in):
```json
{
  "vehicle_id": 5,
  "customer_id": 3,
  "leave_vehicle": true,
  "waiver_accepted": true,
  "waiver_accepted_at": "2025-03-01 10:30:00",
  "payment_mode": "CASH",
  "washboy_name": "Marco",
  "items": [
    { "service_id": 2 },
    { "service_id": 4 },
    { "item_name": "Special Polish", "price_status": "TBA" },
    { "item_name": "Fragrance",      "price_status": "FIXED", "unit_price": 50 }
  ]
}
```

**Item types:**
| Field | Description |
|-------|-------------|
| `{ "service_id": N }` | Catalog service — price auto-resolved from pricing table |
| `{ "item_name": "...", "price_status": "TBA" }` | Custom item, price unknown — quoted later |
| `{ "item_name": "...", "price_status": "FIXED", "unit_price": 50 }` | Custom item with known price |

**Service selection rules enforced server-side:**
- **CAR:** Only 1 package (Package 1/2/3); Complete is exclusive (no packages/addons); add-ons allowed without a package.
- **MOTOR:** Only 1 main motor option; no car packages/addons.

---

#### `GET /api/job-orders?date=2025-03-01&status=OPEN`
Lists job orders, filterable by date and status.

#### `GET /api/job-orders/:id`
Returns full job order with items, computed total, and TBA flags.

Response includes:
```json
{
  "total_amount": 430.00,
  "has_tba": true,
  "tba_count": 1,
  "items": [...]
}
```

#### `PUT /api/job-orders/:id`
Update header fields:
```json
{
  "washboy_name": "Carlo",
  "payment_mode": "GCASH",
  "status": "DONE"
}
```

#### `POST /api/job-orders/:id/cancel`
Sets status to `CANCELLED`.

---

### Job Order Items

#### `POST /api/job-orders/:id/items`
Add a single item to an existing job order (same format as items in POST job order).

#### `PUT /api/job-orders/:id/items/:item_id`
Quote a TBA item (admin use):
```json
{
  "unit_price": 350.00,
  "price_status": "QUOTED"
}
```
The job order total auto-updates on next read.

#### `DELETE /api/job-orders/:id/items/:item_id`

---

### Reports

#### `GET /api/reports/daily?date=2025-03-01`

Returns:
```json
{
  "date": "2025-03-01",
  "orders": [
    {
      "job_order_id": 1,
      "created_at": "2025-03-01 09:14:22",
      "plate_number": "ABC1234",
      "vehicle_size": "MEDIUM",
      "vehicle_category": "CAR",
      "customer_name": "Juan dela Cruz",
      "services": [
        { "item_name": "Package 1", "unit_price": 200.00, "price_status": "FIXED" },
        { "item_name": "Underwash", "unit_price": 200.00, "price_status": "FIXED" }
      ],
      "total_amount": 400.00,
      "has_tba": false,
      "payment_mode": "CASH",
      "washboy_name": "Marco",
      "status": "DONE"
    }
  ],
  "summary": {
    "total_jobs": 12,
    "gross_total": 5400.00,
    "paid_total": 4900.00,
    "unpaid_total": 500.00,
    "by_payment_mode": {
      "CARD": 800.00,
      "CASH": 3100.00,
      "GCASH": 1000.00,
      "UNPAID": 500.00
    }
  }
}
```

---

## Business Rules (Enforced Server-Side)

### Vehicle Size vs. Category
- `vehicle_size` is required for `CAR`, automatically `null` for `MOTOR`.

### Service Selection
| Category | Rule |
|----------|------|
| CAR | Max 1 package (P1 / P2 / P3) |
| CAR | `Complete` (BUNDLE) is exclusive — cannot combine with packages or add-ons |
| CAR | Add-ons (Underwash, Engine Wash) allowed with or without a package |
| MOTOR | Max 1 `MOTOR_MAIN` service |
| MOTOR | Car packages/add-ons are rejected |
| BOTH | `OTHER` services allowed for any vehicle type |

### Waiver
- `leave_vehicle = true` **requires** `waiver_accepted = true` + `waiver_accepted_at` timestamp.

### TBA Items
- `unit_price` must be `null` on creation.
- Admin later calls `PUT /job-orders/:id/items/:item_id` to set the quoted price.
- Total is always `SUM(unit_price)` ignoring `null` rows.

### Payment Modes
`CASH`, `GCASH`, `CARD`, `UNPAID` (receivable/utang)

---

## Seeded Pricing Data

### Motor (fixed)
| Service | Price |
|---------|-------|
| Motor Wash | ₱100 |
| Motor Wash with Wax | ₱120 |

### Car (by size)
| Service | Small | Medium | Large | XL |
|---------|-------|--------|-------|----|
| Package 1 | 180 | 200 | 220 | 250 |
| Package 2 | 220 | 250 | 280 | 310 |
| Package 3 | 260 | 300 | 330 | 360 |
| Underwash | 150 | 200 | 250 | 300 |
| Engine Wash | 200 | 250 | 300 | 350 |
| Complete | 650 | 750 | 900 | 1000 |
