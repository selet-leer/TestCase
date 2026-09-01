# DECISIONS.md

## Update is a full replace (PUT), not a partial patch

`PUT /api/products/{id}` requires `name`, `price`, and `stock` all at once — no partial updates.

The task spec only lists `PUT` for update, not `PATCH`. By REST convention PUT means "replace the whole resource", so I went with that instead of allowing partial fields. If partial updates were needed, that'd really be a PATCH endpoint, which isn't part of the spec here.

It also lines up nicely with the optimistic-concurrency idea from Level 04: the client already did a GET to read the current state (including `version`) before updating, so it has all the fields on hand anyway — classic read-modify-write. And if someone just wants to bump `stock` without touching name/price, that's exactly what the `POST /api/products/{id}/orders` endpoint from Level 02 is for.
