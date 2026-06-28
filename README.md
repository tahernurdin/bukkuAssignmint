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
- [Scaling & performance](#scaling--performance)
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
Route → Controller → FormRequest (validation) → DTO → Service → Repository → Model
                                                    ↘ Resource (response)
```

| Layer | Responsibility | Key classes |
|-------|----------------|-------------|
| Controllers | HTTP only; thin, one per resource | `ProductController`, `PurchaseController`, `SaleController` |
| FormRequests | Shape validation (payload only) | `Store{Purchase,Sale}Request`, `Update{Purchase,Sale}Request` |
| DTOs | Immutable input carriers | `TransactionDTO` |
| Services | Orchestration & the WAC math | `TransactionService`, `Inventory\WacLedgerService` |
| Repositories | Persistence boundary (interface + Eloquent impl) | `Contracts\TransactionRepositoryInterface`, `Eloquent\EloquentTransactionRepository` |
| Resources | Output shaping + display rounding | `PurchaseResource`, `SaleResource`, `ProductResource` |
| Models | Eloquent records + the per-row snapshot | `Transaction`, `Product` |

Services depend on **repository interfaces** (`App\Repositories\Contracts`), bound to Eloquent
implementations in `RepositoryServiceProvider`. This inverts the persistence dependency — the
storage layer is swappable — and lets the WAC engine be unit-tested against an in-memory
repository double with no database.

The heart of the system is **`WacLedgerService::recalculateFrom($productId, $fromDate)`**.
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

> **Scope note:** User registration, login, logout, and token management are **outside the
> scope of this assignment**. The auth endpoints (`/register`, `/login`, `/logout`, `/refresh`,
> `/me`) exist solely to simulate a realistic JWT-gated API so the costing endpoints can be
> exercised. They carry no production-grade validation (e.g. the register endpoint accepts an
> arbitrary role without restriction). In a real system, identity management would be handled
> by a dedicated auth service.

JWT (Bearer). Use the seeded user or register a new one, then send
`Authorization: Bearer <token>` on every costing endpoint.

```bash
# Login → returns the token (seeded user: test@example.com / password)
curl -X POST http://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}'
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
| POST | `/products` | Create a product |
| GET | `/products/{id}` | Show a product |
| PATCH | `/products/{id}` | Update a product |
| DELETE | `/products/{id}` | Delete a product (409 if it has transactions) |
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
  -d '{"product_id":1,"date":"2022-01-01","quantity":"150","buying_price":"2.00"}'
```

```json
{
  "data": {
    "id": 1, "type": "purchase", "date": "2022-01-01", "product_id": 1,
    "product": { "id": 1, "name": "Widget", "sku": "WIDGET-001" },
    "quantity": "150.00", "buying_price": "2.00",
    "wac": "2.00", "quantity_on_hand": "150.00", "value_on_hand": "300.00"
  }
}
```

### Record a sale (returns costing)

```bash
curl -X POST http://127.0.0.1:8000/api/sales \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"product_id":1,"date":"2022-01-07","quantity":"5"}'
```

```json
{
  "data": {
    "id": 3, "type": "sale", "date": "2022-01-07", "product_id": 1,
    "product": { "id": 1, "name": "Widget", "sku": "WIDGET-001" },
    "quantity": "5.00",
    "wac": "1.97", "cost": "9.84",
    "quantity_on_hand": "155.00", "value_on_hand": "305.16"
  }
}
```

A sale carries no price of its own. `cost` is the cost of goods sold; `wac` is the average unit
cost applied.

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
| `date` | required, `YYYY-MM-DD` |
| `quantity` | required, numeric, ≤ 2 decimals, > 0 |
| `buying_price` | required, numeric, ≤ 2 decimals, ≥ 0 — **purchases only** (a sale carries no price) |

The FormRequests validate **shape only**. Two rules are *stateful* — they depend on the rest
of the ledger, not just the payload — so they live in the service/engine, not the request:

- **One live transaction per product per date.** Backed by a DB unique index; a collision is
  surfaced as `422` on `date` (`DuplicateTransactionDateException`). Soft-deleted rows release
  their date for reuse.
- **No overselling.** A sale may not exceed quantity on hand at its date; enforced during WAC
  recalculation and surfaced as `422` on `quantity`.

On update, only `date`, `quantity` and (for purchases) `buying_price` may change — product and
type are fixed for the life of a transaction.

---

## Assumptions & design decisions

- **Independent costing service.** A sale transaction *is* the record of a sale; there is no
  order/checkout flow.
- **One transaction per product per date.** Each product is its own ledger, so two different
  products can both have a transaction on the same date.
- **Single-tenant.** Transactions are not scoped per user; authentication only gates access.
- **High precision internally, 2 dp on display.** Avoids rounding drift; differs from the
  PDF's pre-rounded numbers by design (see [How WAC works](#how-wac-works)).
- **A sale carries no price.** Its cost of goods sold is derived entirely from the WAC at its
  date, so the sale endpoint takes only product, date and quantity — the selling price the
  customer paid is out of scope for a costing ledger.
- **No overselling.** A sale (or a recalculation) that would push quantity on hand below
  zero is rejected with `422` and rolled back.
- **Purchases and sales are independent stacks.** Each kind has its own controller, request
  pair and resource (`{Purchase,Sale}Controller`, `Store/Update{Purchase,Sale}Request`,
  `{Purchase,Sale}Resource`), and all of them are thin: a controller just wires HTTP to
  `TransactionService` with its `TransactionType`, and a request is a flat list of payload
  rules. The shared *behaviour* — the DB transaction, WAC recalculation and oversell rollback —
  lives once in `TransactionService`, not in a controller base, so the duplication across the
  HTTP classes is only a few lines of declaration with no branching. The **resources are kept
  separate on purpose**: a sale exposes cost of goods sold and a purchase does not, so each is
  an explicit, self-documenting response contract rather than one resource with conditional
  fields. (An earlier draft hoisted the controllers under a Template-Method base; flat,
  independent classes proved clearer than the indirection for two kinds that diverge in their
  payload and their response.)

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

## Scaling & performance

A backdated insert (or an update/delete) re-costs the transactions that follow it. The obvious
worry is "what if there are 100,000 rows — does every write recompute all of them?" It does not.

**The recompute is already bounded to the rows a change can actually affect:**

- **Per product.** `recalculateFrom($productId, $fromDate)` replays a *single* product's ledger,
  not the global table. Each product is an independent chain.
- **Only the affected tail.** It seeds from the snapshot of the row immediately before the edited
  date (`snapshotBefore`) and replays forward (`chainFrom`) — so editing near the end of a chain
  rewrites a handful of rows. The full-chain `O(n)` case only happens when you edit at the very
  *start* of one product's history. This is the "efficiently" the bonus asks for: it's an
  **algorithmic** bound, not a faster CPU.
- **Atomic and consistent.** The whole replay runs inside `DB::transaction` with `lockForUpdate`
  row locks, so a reader never sees half-recosted state and concurrent writers can't interleave.

**Where the cost actually is.** The WAC math is a cheap linear pass (a few BCMath ops per row).
The dominant cost is the **writes**: `WacLedgerService::persist()` currently issues one
`UPDATE` per row. At the assignment's scale this is sub-second, so the engine runs
**synchronously** — which is the right call for a costing ledger, where reading back the correct
cost immediately is the entire point.

**If a single product's chain grew into the hundreds of thousands**, the fix order would be:

1. **Batch the writes first.** Replace the per-row `save()` loop with one bulk `upsert`, turning
   N `UPDATE`s into one round trip. This keeps the operation synchronous and correct and removes
   the real bottleneck. *(Not implemented — it would be the first optimisation if profiling
   demanded it.)*
2. **Only then consider a queue.** Moving recompute to a background job does **not** reduce the
   work — it just hides the latency, at the cost of eventual consistency (a sale's cost is briefly
   unknown, needing a `pending` state and a job-status endpoint) and per-product job
   serialisation to stay correct. That trade isn't justified at this scale, so it is deliberately
   **not** built.

---

## Testing

```bash
php artisan test                      # everything
php artisan test --testsuite=Unit     # WAC engine only — no database
```

- **Unit** — the WAC math (assignment example at full precision, sell-out, oversell
  rejection, partial recalculation) runs against an **in-memory repository double, with no
  database** (`tests/Doubles/InMemoryTransactionRepository`).
- **Feature** — the HTTP layer (auth, recording/listing, validation, per-product date
  uniqueness, the bonus recalculation/rollback paths) runs against an in-memory SQLite
  database through the real Eloquent repositories.
