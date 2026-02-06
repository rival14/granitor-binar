# Granitor Binar API

A REST API built with Laravel 12 implementing User management, Product catalog, and Order submission.

## Requirements

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL

## Quick Start

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations and seed sample data
php artisan migrate:fresh --seed

# Start the development server
php artisan serve
```

The seeder creates test accounts with the following roles:

| Role          | Email               | Password   |
|---------------|---------------------|------------|
| Administrator | admin@example.com   | password   |
| Manager       | manager@example.com | password   |

### Obtaining an API Token

Use the login endpoint to obtain a token:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@example.com", "password": "password"}'
```

Alternatively, generate a token using Tinker:

```bash
php artisan tinker
> $user = User::where('email', 'admin@example.com')->first();
> echo $user->createToken('dev')->plainTextToken;
```

Use the token in the `Authorization` header:

```
Authorization: Bearer <your-token>
```

## API Endpoints

### Authentication

| Method | Endpoint      | Description                          |
|--------|---------------|--------------------------------------|
| POST   | `/api/login`  | Authenticate and obtain an API token |

**POST /api/login** — public endpoint (no token required).

Request body:

| Field      | Type   | Required | Description          |
|------------|--------|----------|----------------------|
| `email`    | string | Yes      | User email address   |
| `password` | string | Yes      | User password        |

Returns the authenticated user object and a Sanctum API token. Inactive accounts are rejected.

### Users

| Method | Endpoint           | Description                    |
|--------|--------------------|--------------------------------|
| GET    | `/api/users`       | List active users (paginated)  |
| POST   | `/api/users`       | Create a new user              |
| GET    | `/api/users/{id}`  | Show user details              |
| PUT    | `/api/users/{id}`  | Update a user                  |
| DELETE | `/api/users/{id}`  | Soft-delete a user             |

**GET /api/users** query parameters:

| Parameter       | Type   | Default      | Description                          |
|-----------------|--------|--------------|--------------------------------------|
| `search`        | string | —            | Search by name or email              |
| `page`          | int    | 1            | Page number                          |
| `per_page`      | int    | 15           | Results per page (max 100)           |
| `sort_by`       | string | `created_at` | Sort field: name, email, created_at  |
| `sort_direction` | string | `desc`       | Sort direction: asc, desc            |

Each user in the list includes:
- `orders_count` — total number of orders
- `can_edit` — whether the authenticated user can edit this user

**Authorization rules for `can_edit` / update:**

| Authenticated Role | Can Edit                     |
|--------------------|------------------------------|
| Administrator      | Any user                     |
| Manager            | Only users with role `user`  |
| User               | Only themselves              |

**POST /api/users** also sends:
- Confirmation email to the new user
- Notification email to the system administrator (configured via `MAIL_ADMIN_ADDRESS`)

### Products

| Method | Endpoint              | Description                     |
|--------|-----------------------|---------------------------------|
| GET    | `/api/products`       | List products (paginated)       |
| POST   | `/api/products`       | Create a new product            |
| GET    | `/api/products/{id}`  | Show product details            |
| PUT    | `/api/products/{id}`  | Update a product                |
| DELETE | `/api/products/{id}`  | Soft-delete a product           |

### Orders

| Method | Endpoint      | Description                                |
|--------|---------------|--------------------------------------------|
| GET    | `/api/orders` | List orders for the authenticated user     |
| POST   | `/api/orders` | Submit an order for the authenticated user |

**GET /api/orders** query parameters:

| Parameter  | Type | Default | Description        |
|------------|------|---------|--------------------|
| `page`     | int  | 1       | Page number        |
| `per_page` | int  | 15      | Results per page   |

Returns paginated orders for the authenticated user, ordered by newest first. Each order includes its items with product details.

**POST /api/orders** — order submission:
- Validates stock availability for all items
- Creates the order atomically (all-or-nothing)
- Deducts stock from products
- Snapshots the unit price at the time of order

## Project Structure

```
app/
├── Enums/              # UserRole, OrderStatus (PHP backed enums)
├── Http/
│   ├── Controllers/Api/  # Thin controllers (delegate to services)
│   ├── Requests/         # Form request validation (per domain)
│   └── Resources/        # API response transformations
├── Mail/               # Queueable mailable classes
├── Models/             # Eloquent models with scopes & helpers
├── Policies/           # Authorization rules (UserPolicy)
└── Services/           # Business logic layer
```

### Architecture Decisions

| Decision | Rationale |
|----------|-----------|
| Service layer | Separates business logic from HTTP handling; controllers stay thin |
| PHP backed enums | Type safety, IDE support, easy to extend with new roles/statuses |
| UserPolicy for authorization | Declarative permission rules, testable in isolation |
| `lockForUpdate()` in orders | Prevents race conditions on stock deduction |
| `bcmul`/`bcadd` for money | Avoids floating-point arithmetic issues |
| Soft deletes | Data preservation; products referenced by orders can't disappear |
| `unit_price` on order items | Snapshots price at order time, immune to future price changes |
| Queued mail (`ShouldQueue`) | Non-blocking user creation; emails sent asynchronously |

## Testing

```bash
# Run the full test suite
php artisan test

# Run with coverage (requires PCOV or Xdebug)
php artisan test --coverage

# Run a specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

Test structure:

```
tests/
├── Unit/
│   ├── Enums/       # UserRole and OrderStatus enum logic
│   ├── Models/      # Scopes, helpers, accessors
│   └── Policies/    # UserPolicy authorization matrix
└── Feature/
    └── Api/         # Full HTTP endpoint tests
```

**90 tests, 252 assertions** covering:
- All CRUD operations for Users and Products
- Order submission with stock validation
- Authorization rules (admin, manager, user perspectives)
- Input validation (required fields, uniqueness, types)
- Email dispatch verification
- Pagination, search, and sorting

## Postman Collection

Import `postman_collection.json` from the project root into Postman.

The collection includes all endpoints organized by resource. Set the `base_url` variable in Postman before use. The **Login** request automatically saves the returned token to the `token` collection variable, so subsequent requests are authenticated without manual setup.

## Docker Setup

### Prerequisites

- Docker and Docker Compose

### Getting Started

```bash
# Copy the Docker-ready environment file
cp .env.docker .env

# Build and start all services
docker compose up -d --build

# Seed the database (first run only)
docker compose exec app php artisan db:seed
```

The following services will be available:

| Service  | URL                     | Description           |
|----------|-------------------------|-----------------------|
| API      | http://localhost:8000   | Laravel application   |
| Mailpit  | http://localhost:8025   | Email testing web UI  |
| MySQL    | localhost:3306          | Database              |
| Redis    | localhost:6379          | Cache and queue       |

### Generate an API Token (Docker)

```bash
docker compose exec app php artisan tinker --execute="\
    echo App\Models\User::where('email','admin@example.com')->first()->createToken('dev')->plainTextToken;"
```

### Useful Commands

```bash
# View logs
docker compose logs -f app

# Run migrations
docker compose exec app php artisan migrate

# Run tests
docker compose exec app php artisan test

# Stop all services
docker compose down

# Stop and remove volumes (full reset)
docker compose down -v
```

### Docker Architecture

```
docker-compose.yml
├── app        → FrankenPHP (Caddy + PHP in one process), port 8000
├── queue      → Laravel queue worker (same image, different command)
├── mysql      → MySQL 8.0, persisted volume
├── redis      → Redis 7 (cache + queue broker)
└── mailpit    → SMTP catch-all + web UI, port 8025
```

The application uses [FrankenPHP](https://frankenphp.dev/) — a modern PHP application server built on Caddy. Unlike the traditional Nginx + PHP-FPM + Supervisor stack, FrankenPHP runs as a **single process**, reducing complexity and resource usage.

The `Dockerfile` uses a **multi-stage build**:
1. **vendor** stage — installs Composer dependencies (no dev)
2. **production** stage — FrankenPHP Alpine with the application

The `entrypoint.sh` automatically:
- Generates `APP_KEY` if not set
- Runs database migrations
- Caches config/routes/views in production mode

## Configuration

Key environment variables in `.env`:

| Variable             | Description                        | Default            |
|----------------------|------------------------------------|--------------------|
| `DB_CONNECTION`      | Database driver                    | `sqlite`           |
| `MAIL_MAILER`        | Mail transport                     | `log`              |
| `MAIL_ADMIN_ADDRESS` | Admin notification recipient email | `admin@example.com`|
| `QUEUE_CONNECTION`   | Queue driver for async mail        | `database`         |

For Docker, use `.env.docker` as the starting point — it has all service hostnames preconfigured (`mysql`, `redis`, `mailpit`).
