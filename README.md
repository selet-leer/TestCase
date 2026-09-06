# Product API

A small JSON-backed product catalogue built with Laravel 12. The whole catalogue lives in
a single JSON file — there is no database — which makes concurrent writes the interesting
part of the problem. See [DECISIONS.md](DECISIONS.md) for the reasoning behind the
locking strategy, the query layer and the known limits of this approach.

## Requirements

Docker and Docker Compose. Nothing else — PHP and Composer run inside the container.

To run it without Docker you need PHP 8.2+ and Composer locally.

## Setup

```bash
cp .env.example .env
docker compose up -d
```

The first start installs the Composer dependencies inside the container, so give it a
minute. Then generate the application key:

```bash
docker compose exec app php artisan key:generate
```

The API is now on **http://localhost:8000/api/products**.

No migrations are needed: the catalogue is stored in `storage/app/data/products.json`,
which is created on the first write. The path can be pointed elsewhere with
`PRODUCTS_STORAGE_PATH` in `.env` — the test suite uses this to work on an isolated file.

## Running the tests

```bash
docker compose exec app php artisan test
```

A single test class, or a single test:

```bash
docker compose exec app php artisan test --filter=OrderApiTest
docker compose exec app php artisan test --filter=OrderConcurrencyTest::test_50_concurrent_orders_never_oversell
```

Note that `OrderConcurrencyTest` takes roughly 30 seconds on its own: it spawns 50 real OS
processes that contend on the same file lock, which is the point — see
[DECISIONS.md](DECISIONS.md) for why threads or HTTP requests would not prove anything here.

| Suite | Covers |
| --- | --- |
| `ProductApiTest` | CRUD endpoints, validation, status codes |
| `OrderApiTest` | the order endpoint's HTTP contract (200 / 404 / 409 / 422) |
| `OrderConcurrencyTest` | 50 concurrent orders never oversell |
| `ProductQueryTest` | filtering, sorting, pagination |

## Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/products` | list, filter, sort, paginate |
| `POST` | `/api/products` | create |
| `GET` | `/api/products/{id}` | read one |
| `PUT` | `/api/products/{id}` | replace (all fields required) |
| `DELETE` | `/api/products/{id}` | delete |
| `POST` | `/api/products/{id}/orders` | order a quantity, decrements stock |

A product is `{ id, name, price, stock, version, created_at, updated_at }`. `price` is an
integer in minor units (cents); `version` is bumped on every write.

### Querying the list

```
GET /api/products?filter[stock][gte]=1&filter[name][like]=cable&sort=-price,name&page=2&per_page=20
```

- **filter** — `filter[field][op]=value`, combined with AND.
  Fields: `name`, `price`, `stock`, `version`.
  Operators: `eq`, `ne`, `lt`, `lte`, `gt`, `gte`, `like`.
- **sort** — comma-separated, `-` prefix for descending, e.g. `sort=-price,name`.
- **page** / **per_page** — default 20, clamped to a maximum of 100.

Unknown fields and operators are ignored rather than rejected. The response uses Laravel's
standard paginator envelope (`data`, `links`, `meta`).

### Status codes

| Code | When |
| --- | --- |
| `200` | successful read, update or order |
| `201` | product created |
| `204` | product deleted |
| `404` | product does not exist |
| `409` | order rejected — the request is valid, but stock is insufficient |
| `422` | validation failed |

All errors are returned as JSON (`{"message": ...}`, plus `errors` for validation),
regardless of the client's `Accept` header.
