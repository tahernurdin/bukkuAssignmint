# WAC Inventory & Costing API

A RESTful backend that tracks **purchase** and **sale** transactions and calculates the
cost of every sale using the **Weighted Average Cost (WAC)** method. Built with Laravel 13
and JWT authentication.

This is a focused costing ledger — not an e-commerce store. It records inventory movements
and reports the cost of goods sold; it does not model carts, orders, customers or payments.

---

## Table of contents
- [How WAC works](#how-wac-works)
- [Architecture](#architecture)
- [Setup](#setup)
- [Authentication](#authentication)
- [API reference](#api-reference)
- [Validation rules](#validation-rules)
- [Assumptions & design decisions](#assumptions--design-decisions)
- [Bonus features](#bonus-features)
- [Testing](#testing)

---

## How WAC works

WAC values every sale at the *average* unit cost of everything on hand at that moment.

| Date       | Action            | On hand | Value (RM) | Avg cost (RM) |
|------------|-------------------|--------:|-----------:|--------------:|
| 2022-01-01 | Buy 150 @ 2.00    |     150 |     300.00 |       2.000000 |
| 2022-01-05 | Buy 10 @ 1.50     |     160 |     315.00 |       1.968750 |
| 2022-01-07 | Sell 5            |     155 |     305.16 |       1.968750 |

The sale's **cost of goods sold** = `1.968750 × 5 = 9.84` (the average is unchanged by a
sale). Value on hand drops by that cost: `315 − 9.84 = 305.16`.

> **Precision note:** the average is stored at high precision (6 dp) and only rounded to
> 2 dp for display. The assignment PDF instead rounds the average to `1.97` *before*
> multiplying, which yields `9.85` / `305.15`. Computing from the unrounded average
> (`9.84` / `305.16`) is deliberate — it prevents rounding error from accumulating across a
> long chain of transactions. See [Assumptions](#assumptions--design-decisions).

---

## Architecture

Clean, layered separation. A request flows:

```
Route → Controller → FormRequest (validation) → DTO → Service → Model
                                                    ↘ Resource (response)
```

| Layer | Responsibility | Key classes |
|-------|----------------|-------------|
| Controllers | HTTP only; pick the transaction type | `ProductController`, `PurchaseController`, `SaleController` |
| FormRequests | Stateless validation | `StoreTransactionRequest`, `UpdateTransactionRequest` |
| DTOs | Immutable input carriers | `TransactionDTO` |
| Services | Orchestration & the WAC math | `TransactionService`, `Inventory\WacLedgerService` |
| Resources | Output shaping + display rounding | `PurchaseResource`, `SaleResource`, `ProductResource` |
| Models | Persistence + the per-row snapshot | `Transaction`, `Product` |

The heart of the system is **`WacLedgerService::recalculateFrom($product, $fromDate)`**.
Each transaction row stores a *snapshot* of the inventory state right after it
(`quantity_on_hand`, `value_on_hand`, `wac_at_time`, and `calculated_cost` for sales).
To (re)compute, the engine seeds from the snapshot of the row just before `$fromDate` and
replays forward — so it only ever touches the rows a change can actually affect. All money
math uses **BCMath** to avoid binary-float drift.

---

## Setup

**Requirements:** PHP 8.4+ (with `bcmath`), Composer. SQLite or MySQL.

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret          # generates JWT_SECRET

# 3. Database — easiest path is SQLite (the .env.example default)
touch database/database.sqlite
#    ...or point .env at MySQL (DB_CONNECTION=mysql, DB_DATABASE=..., etc.)

# 4. Migrate + seed dummy products and a test user
php artisan migrate:fresh --seed

# 5. Serve
php artisan serve               # http://127.0.0.1:8000
```

Seeded test user: `test@example.com` (password is the factory default `password`). Seeded
products: Widget, Gadget, Gizmo, Doohickey, Thingamajig.

---

## Authentication

JWT (Bearer). Register or log in, then send `Authorization: Bearer <token>` on every other
endpoint.

```bash
# Register
curl -X POST http://127.0.0.1:8000/api/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Demo","email":"demo@example.com","password":"password123"}'

# Login → returns the token
curl -X POST http://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"password123"}'
```

```json
{ "access_token": "eyJ0eXAi...", "token_type": "bearer", "expires_in": 3600 }
```

---

## API reference

All routes are prefixed with `/api`. Every endpoint except register/login requires a Bearer
token. Resource responses are wrapped in a `data` key.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/register` | Create a user |
| POST | `/login` | Obtain a JWT |
| POST | `/logout` | Invalidate the current token |
| POST | `/refresh` | Refresh the token |
| GET | `/me` | Current user |
| GET | `/products` | List products |
| GET | `/products/{id}` | Show a product |
| GET | `/purchases` | List purchases (oldest first) |
| POST | `/purchases` | Record a purchase |
| PATCH | `/purchases/{id}` | Update a purchase *(bonus)* |
| DELETE | `/purchases/{id}` | Delete a purchase *(bonus)* |
| GET | `/sales` | List sales **with costing** (oldest first) |
| POST | `/sales` | Record a sale |
| PATCH | `/sales/{id}` | Update a sale *(bonus)* |
| DELETE | `/sales/{id}` | Delete a sale *(bonus)* |

### Record a purchase

```bash
curl -X POST http://127.0.0.1:8000/api/purchases \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"product_id":1,"date":"2022-01-01","quantity":"150","price":"2.00"}'
```

```json
{
  "data": {
    "id": 1, "type": "purchase", "date": "2022-01-01", "product_id": 1,
    "product": { "id": 1, "name": "Widget", "sku": "WIDGET-001" },
    "quantity": "150.00", "price": "2.00",
    "wac": "2.00", "quantity_on_hand": "150.00", "value_on_hand": "300.00"
  }
}
```

### Record a sale (returns costing)

```bash
curl -X POST http://127.0.0.1:8000/api/sales \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"product_id":1,"date":"2022-01-07","quantity":"5","price":"5.00"}'
```

```json
{
  "data": {
    "id": 3, "type": "sale", "date": "2022-01-07", "product_id": 1,
    "product": { "id": 1, "name": "Widget", "sku": "WIDGET-001" },
    "quantity": "5.00", "price": "5.00",
    "wac": "1.97", "cost": "9.84",
    "quantity_on_hand": "155.00", "value_on_hand": "305.16"
  }
}
```

`cost` is the cost of goods sold; `wac` is the average unit cost applied.

### Error responses

`422 Unprocessable Entity` — validation or an oversell:

```json
{
  "message": "Insufficient stock to record this sale.",
  "errors": { "quantity": ["Insufficient stock for product 1 on 2022-01-02: tried to sell 15.00 but only 10.00 on hand."] }
}
```

Other codes: `401` (missing/invalid token), `404` (unknown id, or wrong-type id on a
purchase/sale endpoint), `204` (successful delete).

---

## Validation rules

| Field | Rules |
|-------|-------|
| `product_id` | required, must exist |
| `date` | required, `YYYY-MM-DD`, unique **per product** |
| `quantity` | required, numeric, ≤ 2 decimals, > 0 |
| `price` | required, numeric, ≤ 2 decimals, ≥ 0 |

Stateful rules enforced by the engine (not the request): a sale may not exceed quantity on
hand at its date. On update, only `date`, `quantity` and `price` may change.

---

## Assumptions & design decisions

- **Independent costing service.** A sale transaction *is* the record of a sale; there is no
  order/checkout flow.
- **One transaction per product per date.** Each product is its own ledger, so two different
  products can both have a transaction on the same date.
- **Single-tenant.** Transactions are not scoped per user; authentication only gates access.
- **High precision internally, 2 dp on display.** Avoids rounding drift; differs from the
  PDF's pre-rounded numbers by design (see [How WAC works](#how-wac-works)).
- **Sale price is recorded for reference only.** Cost of goods sold is derived from WAC,
  independent of the price the customer paid.
- **No overselling.** A sale (or a recalculation) that would push quantity on hand below
  zero is rejected with `422` and rolled back.

---

## Bonus features

Both optional bonuses are implemented, and both reuse the single recalculation engine:

1. **Out-of-order inserts.** A transaction may be created with any date; the engine
   recalculates the WAC snapshots of all later transactions efficiently (only the affected
   tail is replayed).
2. **Update / delete.** Existing transactions can be updated or deleted; downstream rows are
   recosted. If the change would cause a downstream sale to oversell, the whole operation
   rolls back.

---

## Testing

```bash
php artisan test
```

Covers the WAC math (the assignment example at full precision, sell-out, oversell rejection,
partial recalculation) and the HTTP layer (auth, recording/listing, validation, per-product
date uniqueness, and the bonus recalculation/rollback paths). Tests run against an in-memory
SQLite database.
