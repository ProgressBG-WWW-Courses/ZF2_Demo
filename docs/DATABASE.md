# Database Structure

Reference for the MariaDB database used by the ZF2 Hotel Booking Demo.

---

## Table of Contents

1. [Overview](#overview)
2. [Connection Configuration](#connection-configuration)
3. [Schema: `payment_orders`](#schema-payment_orders)
4. [Indexes](#indexes)
5. [Data Access Patterns](#data-access-patterns)
6. [State Values](#state-values)
7. [Schema Initialization](#schema-initialization)
8. [Room Data (In-Memory)](#room-data-in-memory)
9. [Connecting to the Database](#connecting-to-the-database)
10. [Common Queries](#common-queries)

---

## Overview

| Property        | Value                 |
|-----------------|-----------------------|
| Database Engine | MariaDB 10.11         |
| Database Name   | `hotel_db`            |
| Character Set   | `utf8mb4`             |
| Storage Engine  | InnoDB                |
| Docker Service  | `db`                  |
| Internal Port   | 3306                  |
| Host Port       | 3309                  |

The database currently has one table (`payment_orders`) for persisting Revolut payment state. Room data is stored in-memory within `RoomService` (not in the database).

---

## Connection Configuration

### From within Docker (app -> db)

Configured via `.env` and loaded by `config/autoload/payment.global.php`:

| Parameter  | Value        | .env Variable  |
|------------|--------------|----------------|
| Host       | `db`         | `DB_HOST`      |
| Port       | `3306`       | `DB_PORT`      |
| Database   | `hotel_db`   | `DB_NAME`      |
| User       | `hotel_user` | `DB_USER`      |
| Password   | `hotel_pass` | `DB_PASSWORD`  |

PDO DSN: `mysql:host=db;port=3306;dbname=hotel_db;charset=utf8mb4`

PDO options:
```php
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
PDO::ATTR_EMULATE_PREPARES   => false
```

### From host machine

| Parameter  | Value        |
|------------|--------------|
| Host       | `localhost`  |
| Port       | `3309`       |
| Database   | `hotel_db`   |
| User       | `hotel_user` |
| Password   | `hotel_pass` |
| Root Password | `rootpass` |

---

## Schema: `payment_orders`

**Source file:** `data/sql/001_payment_orders.sql`

```sql
CREATE TABLE IF NOT EXISTS payment_orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     VARCHAR(255)   NOT NULL UNIQUE,
    room_id      INT            NOT NULL,
    amount       DECIMAL(10, 2) NOT NULL,
    currency     VARCHAR(3)     NOT NULL DEFAULT 'GBP',
    state        VARCHAR(20)    NOT NULL DEFAULT 'PENDING',
    checkout_url TEXT,
    created_at   DATETIME       NOT NULL,
    updated_at   DATETIME       NOT NULL,
    INDEX idx_room_id (room_id),
    INDEX idx_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Column Reference

| Column         | Type            | Nullable | Default   | Description                                |
|----------------|-----------------|----------|-----------|--------------------------------------------|
| `id`           | INT             | NO       | AUTO_INCREMENT | Internal primary key                  |
| `order_id`     | VARCHAR(255)    | NO       | --        | Revolut order ID (UUID format), **UNIQUE** |
| `room_id`      | INT             | NO       | --        | Room identifier (matches RoomEntity ID)    |
| `amount`       | DECIMAL(10, 2)  | NO       | --        | Payment amount in major units (e.g. 50.00) |
| `currency`     | VARCHAR(3)      | NO       | `'GBP'`  | ISO 4217 currency code                     |
| `state`        | VARCHAR(20)     | NO       | `'PENDING'` | Current payment state                   |
| `checkout_url` | TEXT            | YES      | NULL      | Revolut hosted checkout page URL           |
| `created_at`   | DATETIME        | NO       | --        | Record creation timestamp                  |
| `updated_at`   | DATETIME        | NO       | --        | Last state change timestamp                |

### Design Decisions

- **`order_id` as VARCHAR:** Revolut generates UUID-format order IDs (e.g. `6516c652-d1bd-a0c7-8e78-b08b940a40b0`), not integers.
- **`DECIMAL(10,2)` for amount:** Prevents floating-point rounding errors with monetary values. Stores up to 99,999,999.99.
- **`checkout_url` as TEXT:** Revolut checkout URLs can be long and variable-length.
- **Both `created_at` and `updated_at`:** Enables tracking order age for the API fallback logic (30-second threshold in `statusAction`).
- **No foreign key to rooms:** Room data is in-memory, not in the database. The `room_id` column is a logical reference.

---

## Indexes

| Index Name    | Column(s) | Type   | Purpose                                      |
|---------------|-----------|--------|----------------------------------------------|
| PRIMARY       | `id`      | PRIMARY | Row identification                          |
| `order_id`    | `order_id`| UNIQUE | Fast lookup by Revolut order ID             |
| `idx_room_id` | `room_id` | INDEX  | Fast lookup for "latest payment for room" query |
| `idx_state`   | `state`   | INDEX  | Efficient filtering by payment state        |

---

## Data Access Patterns

All database access is through `PaymentService` using PDO prepared statements.

### INSERT -- Create Payment Order

```sql
INSERT INTO payment_orders
    (order_id, room_id, amount, currency, state, checkout_url, created_at, updated_at)
VALUES
    (:order_id, :room_id, :amount, :currency, :state, :checkout_url, :created_at, :updated_at)
```

Called by: `PaymentService::createOrder()`

### SELECT -- Get Payment by Order ID

```sql
SELECT * FROM payment_orders WHERE order_id = :order_id LIMIT 1
```

Called by: `PaymentService::getPaymentByOrderId()`

### SELECT -- Get Latest Payment for Room

```sql
SELECT * FROM payment_orders
 WHERE room_id = :room_id
 ORDER BY created_at DESC
 LIMIT 1
```

Called by: `PaymentService::getLatestPaymentForRoom()`

### UPDATE -- Update Payment State (Atomic)

```sql
UPDATE payment_orders
   SET state = :state, updated_at = :updated_at
 WHERE order_id = :order_id
   AND state NOT IN ('COMPLETED', 'FAILED', 'CANCELLED')
```

Called by: `PaymentService::updatePaymentState()`

The `NOT IN` clause ensures terminal states are never overwritten, preventing:
- Duplicate webhook deliveries from corrupting state
- Race conditions between webhook handler and API polling fallback

---

## State Values

| State        | Description                              | Terminal? |
|--------------|------------------------------------------|-----------|
| `PENDING`    | Order created, awaiting payment          | No        |
| `AUTHORISED` | Card authorized, not yet captured        | No        |
| `COMPLETED`  | Payment fully captured and settled       | Yes       |
| `FAILED`     | Card declined or payment failed          | Yes       |
| `CANCELLED`  | Payment cancelled by user or merchant    | Yes       |

### State Transitions

```
PENDING ──────> AUTHORISED ──────> COMPLETED
   │
   ├──────> FAILED
   │
   └──────> CANCELLED
```

- Transitions are enforced at the SQL level (terminal states excluded from UPDATE)
- The application does not allow transition from one terminal state to another

---

## Schema Initialization

The database schema is automatically initialized when the MariaDB container starts for the first time:

1. `docker-compose.yml` mounts `./data/sql` to `/docker-entrypoint-initdb.d`
2. MariaDB executes all `.sql` files in that directory on first boot
3. The `001_` prefix ensures execution order (useful if more migration files are added)
4. `CREATE TABLE IF NOT EXISTS` makes the script idempotent

To force re-initialization (reset the database):

```bash
docker compose down -v       # Remove containers and volumes
docker compose up -d         # Recreate with fresh database
```

---

## Room Data (In-Memory)

Room data is **not stored in the database**. It is hardcoded in `RoomService` as an in-memory array.

| id | number | type   | price | description                           |
|----|--------|--------|-------|---------------------------------------|
| 1  | 101    | Single | 50    | Cozy single room with garden view     |
| 2  | 102    | Double | 80    | Spacious double room with balcony     |
| 3  | 201    | Suite  | 150   | Luxury suite with sea view and jacuzzi|
| 4  | 202    | Double | 90    | Double room with mountain view        |
| 5  | 301    | Suite  | 200   | Presidential suite with private terrace|

The `RoomEntity` class includes Doctrine ORM annotations for a `rooms` table, but this table does not exist in the database. The annotations serve as documentation for how the entity would be mapped in a production setup.

### Hypothetical `rooms` Table (from Doctrine annotations)

```sql
-- Not created in the database; shown for reference only
CREATE TABLE rooms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    number      VARCHAR(10)   NOT NULL,
    type        VARCHAR(50)   NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    description VARCHAR(255)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Connecting to the Database

### Via Docker (recommended)

```bash
# Interactive MySQL shell
docker compose exec db mysql -u hotel_user -photel_pass hotel_db

# Run a single query
docker compose exec -T db mysql -u hotel_user -photel_pass hotel_db \
    -e "SELECT * FROM payment_orders" --batch
```

### Via host machine

```bash
mysql -h 127.0.0.1 -P 3309 -u hotel_user -photel_pass hotel_db
```

### Via GUI tools (DBeaver, TablePlus, etc.)

| Setting  | Value       |
|----------|-------------|
| Host     | `127.0.0.1` |
| Port     | `3309`      |
| Database | `hotel_db`  |
| User     | `hotel_user`|
| Password | `hotel_pass`|

---

## Common Queries

```sql
-- List all payment orders
SELECT id, order_id, room_id, amount, currency, state, created_at, updated_at
  FROM payment_orders
 ORDER BY created_at DESC;

-- Find payments for a specific room
SELECT * FROM payment_orders
 WHERE room_id = 1
 ORDER BY created_at DESC;

-- Count payments by state
SELECT state, COUNT(*) as count
  FROM payment_orders
 GROUP BY state;

-- Find pending payments older than 5 minutes (potentially stale)
SELECT * FROM payment_orders
 WHERE state = 'PENDING'
   AND created_at < NOW() - INTERVAL 5 MINUTE;

-- Clear all payment data (for testing)
TRUNCATE TABLE payment_orders;

-- Check latest payment for each room
SELECT po.*
  FROM payment_orders po
 INNER JOIN (
     SELECT room_id, MAX(created_at) as max_created
       FROM payment_orders
      GROUP BY room_id
 ) latest ON po.room_id = latest.room_id AND po.created_at = latest.max_created;
```
