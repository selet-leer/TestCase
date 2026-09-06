# DECISIONS.md

## Locking and atomic writes (Level 02)

**Strategy.** `create`, `update`, `delete` and `decrementStock` take an exclusive
`flock(LOCK_EX)` on `products.json.lock` before reading, and hold it until the method
returns. The file is never written in place: `write()` dumps JSON to `products.json.tmp`
and renames it over `products.json`.

**Why the lock.** An order is read-modify-write: read stock, check it is enough, subtract,
write. Without serialisation two requests both read `stock = 1`, both pass the check, both
write `stock = 0`. Oversold, one update lost. The lock makes check and write one critical
section. On insufficient stock the exception is thrown before `write()`, so a rejected
order changes nothing on disk.

**Why the rename.** `file_put_contents` is not atomic. A reader — `GET /products` takes no
lock by design — could catch the file half-written. `rename()` on the same filesystem is
atomic: the reader sees the whole old file or the whole new one. That is what keeps reads
lock-free.

**Releasing.** `unlock()` runs in a `finally` and does not throw. An exception there would
replace the one in flight and turn a clean 409 into a 500. `lock()` does throw: if the lock
cannot be taken, the critical section must not be entered.

**Test.** `OrderConcurrencyTest` uses 50 OS processes, not 50 requests. PHP has no threads
in a standard build, so real parallelism means real processes. An HTTP version would need
PHP-FPM; the built-in dev server serialises requests and would pass without any locking.

## Query at scale (Level 03)

**Cost.** No index, no query planner. Every request reads the whole file, decodes it,
hydrates every row, then filters, sorts and slices in PHP. That is O(n) per call, even for
one that returns nothing. Sorting adds O(n log n). The decoded array and the objects sit in
memory at once.

**As it grows:**

- **10k** — a few MB, tens of milliseconds per request. Works, but every call is a full
  scan, and each write blocks all others for a full rewrite.
- **100k** — tens of MB and 100-300 ms per request before any filtering. Under load the
  server mostly re-parses the same file.
- **1M** — hundreds of MB and seconds per call, with a real risk of hitting `memory_limit`.
  Not usable as an API.

**Evolution.** The `ProductRepository` interface is the seam; controllers and `ProductQuery`
stay as they are. An `EloquentProductRepository` maps each filter to a `where`, each sort to
an `orderBy`, and pagination to `->paginate()` — which already returns a
`LengthAwarePaginator`, so the controller is untouched. Add indexes on the filtered and
sorted columns, plus a `LIKE`-friendly or full-text index for `name`. Concurrency then comes
free: row locks and transactions replace `flock` and the temp file, and `decrementStock`
becomes `UPDATE ... WHERE stock >= :q`.

The JSON file is fine for hundreds of rows. Beyond that it should be a database.

## Level 04 on paper: robustness

I built Level 03, so this is the written answer to Level 04. I would take corruption
recovery and idempotent creates.

**Corruption recovery — half done.** `read()` refuses to serve a file it cannot parse. It
does not treat it as empty. That matters: reading empty would let the next `create()`
rename a one-product file over the damaged one, and the original data would be gone.

Two gaps. First, there is nothing to fall back to. Every write already builds a complete
new file and swaps it in with `rename()`; copying the current file to `products.json.bak`
just before that leaves a known-good previous state for `read()` to use. Second, a damaged
file ends as a generic `500`. It should be its own exception type mapped to a `503` that
names the problem.

**Idempotent creates.** A client POSTs, the connection drops, the client retries. Today
that creates two identical products.

Fix: the caller sends `Idempotency-Key: <uuid>`. The server keeps a map from key to product
id in the same JSON file. Lookup and create run inside the lock `create()` already takes —
no key: behave as today; key known: return the existing product; key unknown: create,
record `key -> id`, write both together. Two simultaneous retries therefore cannot both
create.

Two details. Keys must expire, or the map grows forever; 24 hours is the usual window, and
expired entries can be dropped on the next write. And the same key with a different body
must be refused with `422` — storing a hash of the body next to the key makes that possible.

## What this design does well, and where it falls apart

**Does well.**

- Controllers never touch the file. They know only the `ProductRepository` interface.
  Swapping in a database is one new class and one line in the service provider.
- Concurrent orders cannot oversell. Check and write happen under the same lock.
- Readers never see a half-written file, because writes are swapped in with a rename.
- Every error is JSON in the same shape, with a sensible status code.

**Falls apart.**

- **No login, no permissions.** Any caller can create, change or delete any product. It was
  not asked for, but the API cannot go online like this.
- **A small change costs as much as a big one.** One order rewrites the whole file. The lock
  covers the whole catalogue, so an order for one product blocks an order for another.
- **Nothing records that an order happened.** Stock goes down, and that is all. No list of
  orders, no what, no when. If a number looks wrong later, there is no way to find out why.
- **No rate limit.** Every request parses the whole file, even one that returns nothing.
  With no login on top, one caller can keep the server busy cheaply.
- **No filtering or sorting by date.** `created_at` and `updated_at` are not allowed fields,
  so "newest first" is impossible.

## What I'd do with more time, and the first thing I'd replace in production

- **Record the orders.** An order currently only lowers a number. A list of orders — which
  product, how many, when — would make stock explainable instead of edited in place. That is
  also the fix for the missing audit trail above.
- **First replacement in production: the JSON file.** Everything above it can stay. The
  repository interface reduces the change to one new implementation. See "Query at scale"
  for when and what.

## Use of AI

Used in these places:

- review of the finished state of Level 0-1 against the requirements, later a second one
  over Level 2-3
- Level 2: discussion of the locking options, `decrementStock()`, order endpoint,
  `order:place` command
- the accompanying feature tests
- Level 3: query layer — `ProductQuery`, `paginate()` and its helper methods in the
  repository
- fixing a test that failed after the Level 3 rework
- drafts for `README.md` and individual paragraphs of this file
- two Git commit messages

Changed in the output:

**Locking.** Two suggestions rejected. The first passed the whole operation in as a closure
— too indirect. The second returned the lock handle and relied on PHP releasing it at the
end of the method; there the variable looks unused. Now there is a visible `lock()` /
`unlock()` pair with `unlock()` in a `finally`.

**Business logic moved.** The stock check sat in the repository. It belongs on
`Product::withOrder()`.

**`per_page`.** Was validated as `max:100`, so a 422. I turned that into clamping: the task
does not forbid large values, and a clamped value returns an answer instead of an error.
Rule, test and documentation adjusted accordingly.

**Contradiction removed.** `update()` had `?? $product->name` fallbacks.

**Readability.** `applyFilters` and the counting in the concurrency test came back as
closures inside nested function calls — both are plain loops now. `is_scalar()` became
`is_string()`. Bare exit codes became named constants. When splitting up the storage layer I
insisted on a pure move, so that the diff stays reviewable.

**Error handling.** I took the `write()` block and adapted it. Two things stayed. First, the
check that everything really was written: if the disk is full, PHP writes part of the data
and reports the character count back, not "error" — hence the comparison against the
expected length. Second, deleting the temporary file on failure. `read()` and the checks in
`lock()` and `unlock()` are mine; `read()` checks whether what was read is a list at all,
which also catches valid JSON that is not a product list.

**Fatal error found.** The test draft named a method `seed()`, which collides with
`Illuminate\Foundation\Testing\TestCase::seed()` and blocked the suite. The static review
had read the file and missed the collision — it only surfaced on the test run. Static
analysis does not replace running the code.
