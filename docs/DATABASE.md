# Database Structure

Reference for the MariaDB database used by the ZF2 Hotel Booking Demo.

---

## Table of Contents

1. [Overview](#overview)
2. [Connection Configuration (Doctrine ORM)](#connection-configuration-doctrine-orm)
3. [Schema: `rooms`](#schema-rooms)
4. [Schema: `users`](#schema-users)
5. [Schema: `payment_orders`](#schema-payment_orders)
6. [Payment State Values](#payment-state-values)
7. [Data Access via Doctrine](#data-access-via-doctrine)
8. [Schema Initialization](#schema-initialization)
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
| ORM             | Doctrine ORM 2.7 via `doctrine/doctrine-orm-module` |
| Docker Service  | `db`                  |
| Internal Port   | 3306                  |
| Host Port       | 3309                  |

The database has three tables:

| Table             | Entity Class              | Module  | Purpose                              |
|-------------------|---------------------------|---------|--------------------------------------|
| `rooms`           | `Room\Entity\RoomEntity`  | Room    | Hotel room records                   |
| `users`           | `Auth\Entity\UserEntity`  | Auth    | User accounts for login              |
| `payment_orders`  | `Payment\Entity\PaymentOrder` | Payment | Revolut payment order state      |

---

## Connection Configuration (Doctrine ORM)

### Doctrine connection (app -> db)

Configured via `.env` and loaded by `config/autoload/db.global.php`:

| Parameter  | Value        | .env Variable  |
|------------|--------------|----------------|
| Host       | `db`         | `DB_HOST`      |
| Port       | `3306`       | `DB_PORT`      |
| Database   | `hotel_db`   | `DB_NAME`      |
| User       | `hotel_user` | `DB_USER`      |
| Password   | `hotel_pass` | `DB_PASSWORD`  |

Doctrine connection config:
```php
'doctrine' => [
    'connection' => [
        'orm_default' => [
            'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
            'params' => [
                'host'     => 'db',
                'port'     => '3306',
                'dbname'   => 'hotel_db',
                'user'     => 'hotel_user',
                'password' => 'hotel_pass',
                'charset'  => 'utf8mb4',
            ],
        ],
    ],
],
```

All modules share a single `Doctrine\ORM\EntityManager` instance provided by `DoctrineORMModule`. Each module registers its entity paths via an `AnnotationDriver` in its `module.config.php`.

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

## Schema: `rooms`

**Source file:** `data/sql/002_rooms.sql`
**Entity:** `Room\Entity\RoomEntity`

### Column Reference

| Column         | Type            | Nullable | Description                          |
|----------------|-----------------|----------|--------------------------------------|
| `id`           | INT             | NO       | Auto-increment primary key           |
| `number`       | VARCHAR(10)     | NO       | Room number (unique)                 |
| `type`         | VARCHAR(50)     | NO       | Room type: Single, Double, or Suite  |
| `price`        | DECIMAL(10, 2)  | NO       | Price per night                      |
| `description`  | VARCHAR(255)    | NO       | Human-readable room description      |

### Indexes

| Index Name                | Column(s)  | Type   |
|---------------------------|------------|--------|
| PRIMARY                   | `id`       | PRIMARY |
| `UNIQ_...` (number)       | `number`   | UNIQUE |
| `idx_type`                | `type`     | INDEX  |

### Seed Data

| id | number | type   | price  | description                           |
|----|--------|--------|--------|---------------------------------------|
| 1  | 101    | Single | 50.00  | Cozy single room with garden view     |
| 2  | 102    | Double | 80.00  | Spacious double room with balcony     |
| 3  | 201    | Suite  | 150.00 | Luxury suite with sea view and jacuzzi|
| 4  | 202    | Double | 90.00  | Double room with mountain view        |
| 5  | 301    | Suite  | 200.00 | Presidential suite with private terrace|

---

## Schema: `users`

**Source file:** `data/sql/003_users.sql`
**Entity:** `Auth\Entity\UserEntity`

### Column Reference

| Column          | Type            | Nullable | Description                        |
|-----------------|-----------------|----------|------------------------------------|
| `id`            | INT             | NO       | Auto-increment primary key         |
| `username`      | VARCHAR(50)     | NO       | Login username (unique)            |
| `password_hash` | VARCHAR(255)    | NO       | bcrypt hash of the password        |
| `role`          | VARCHAR(20)     | NO       | User role for ACL (guest/staff/admin) |

### Indexes

| Index Name                | Column(s)   | Type   |
|---------------------------|-------------|--------|
| PRIMARY                   | `id`        | PRIMARY |
| `UNIQ_...` (username)     | `username`  | UNIQUE |
| `idx_username`            | `username`  | INDEX  |

### Seed Data

| id | username | role  | password (plaintext) |
|----|----------|-------|----------------------|
| 1  | admin    | admin | admin123             |
| 2  | staff    | staff | staff123             |

Passwords are stored as bcrypt hashes generated by `password_hash()`.

---

## Schema: `payment_orders`

**Source file:** `data/sql/001_payment_orders.sql`
**Entity:** `Payment\Entity\PaymentOrder`

### Column Reference

| Column         | Type            | Nullable | Description                                |
|----------------|-----------------|----------|--------------------------------------------|
| `id`           | INT             | NO       | Internal primary key (auto-increment)      |
| `order_id`     | VARCHAR(255)    | NO       | Revolut order ID (UUID format), **UNIQUE** |
| `room_id`      | INT             | NO       | Room identifier (matches rooms.id)         |
| `amount`       | DECIMAL(10, 2)  | NO       | Payment amount in major units (e.g. 50.00) |
| `currency`     | VARCHAR(3)      | NO       | ISO 4217 currency code                     |
| `state`        | VARCHAR(20)     | NO       | Current payment state                      |
| `checkout_url` | TEXT            | YES      | Revolut hosted checkout page URL           |
| `created_at`   | DATETIME        | NO       | Record creation timestamp                  |
| `updated_at`   | DATETIME        | NO       | Last state change timestamp                |

### Indexes

| Index Name                | Column(s)  | Type   |
|---------------------------|------------|--------|
| PRIMARY                   | `id`       | PRIMARY |
| `UNIQ_...` (order_id)     | `order_id` | UNIQUE |
| `idx_room_id`             | `room_id`  | INDEX  |
| `idx_state`               | `state`    | INDEX  |

### Design Decisions

- **`order_id` as VARCHAR:** Revolut generates UUID-format order IDs (e.g. `6516c652-d1bd-a0c7-8e78-b08b940a40b0`), not integers.
- **`DECIMAL(10,2)` for amount:** Prevents floating-point rounding errors with monetary values. Stores up to 99,999,999.99.
- **`checkout_url` as TEXT:** Revolut checkout URLs can be long and variable-length.
- **Both `created_at` and `updated_at`:** Enables tracking order age for the API fallback logic (30-second threshold in `statusAction`).

---

## Payment State Values

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

- Transitions are enforced at the application level (PaymentService checks for terminal states before updating)
- The application does not allow transition from one terminal state to another

---

## Data Access via Doctrine

All database access is through Doctrine's EntityManager, injected into services via factories.

### Room Module

| Service Method                          | Doctrine Call                                      |
|-----------------------------------------|---------------------------------------------------|
| `RoomService::getAll()`                 | `$em->getRepository(...)->findBy([], ['id'=>'ASC'])` |
| `RoomService::getById($id)`            | `$em->find('Room\Entity\RoomEntity', $id)`         |
| `RoomService::save($room)`             | `$em->persist($room); $em->flush();`               |
| `RoomService::search($type, $min)`     | QueryBuilder with optional `WHERE` clauses         |

### Auth Module

| Service Method                          | Doctrine Call                                      |
|-----------------------------------------|---------------------------------------------------|
| `UserService::findByUsername($name)`    | `$em->getRepository(...)->findOneBy(['username'=>$name])` |

### Payment Module

| Service Method                          | Doctrine Call                                      |
|-----------------------------------------|---------------------------------------------------|
| `PaymentService::createOrder(...)`      | `$em->persist($order); $em->flush();`              |
| `PaymentService::getPaymentByOrderId($id)` | `$em->getRepository(...)->findOneBy(['orderId'=>$id])` |
| `PaymentService::getLatestPaymentForRoom($id)` | `$em->getRepository(...)->findOneBy(['roomId'=>$id], ['createdAt'=>'DESC'])` |
| `PaymentService::updatePaymentState($id, $s)` | Find entity, check terminal states, set + flush |

---

## Schema Initialization

The database schema is automatically initialized when the MariaDB container starts for the first time:

1. `docker-compose.yml` mounts `./data/sql` to `/docker-entrypoint-initdb.d`
2. MariaDB executes all `.sql` files in that directory on first boot (alphabetical order)
3. The `001_`, `002_`, `003_` prefixes ensure correct execution order
4. `CREATE TABLE IF NOT EXISTS` makes the scripts idempotent

SQL files:

| File                       | Creates              | Seeds              |
|----------------------------|----------------------|--------------------|
| `001_payment_orders.sql`   | `payment_orders`     | (none)             |
| `002_rooms.sql`            | `rooms`              | 5 hotel rooms      |
| `003_users.sql`            | `users`              | admin + staff users |

To force re-initialization (reset the database):

```bash
docker compose down -v       # Remove containers and volumes
docker compose up -d         # Recreate with fresh database
```

You can also validate the Doctrine schema against the database:

```bash
docker compose exec app php vendor/bin/doctrine-module orm:validate-schema
```

---

## Connecting to the Database

### Via Docker (recommended)

```bash
# Interactive MySQL shell
docker compose exec db mysql -u hotel_user -photel_pass hotel_db

# Run a single query
docker compose exec -T db mysql -u hotel_user -photel_pass hotel_db \
    -e "SELECT * FROM rooms" --batch
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
-- List all rooms
SELECT * FROM rooms ORDER BY id;

-- List all users (without password hashes)
SELECT id, username, role FROM users;

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
