# WGH Intelligence

The consolidated selling picture for shop.websitesgh.com. One question at every
level: what do I do next to sell more at profit.

The contract is `docs/ENGINEERING-SPEC.md`. The visual map is
`docs/system-overview.html`.

**Sprint 1 is built.** Foundation, the full data model, and the WooCommerce
connector. Sprints 2 to 6 are specced and not started.

```
dashboard/
  api/                 Laravel application (PHP 8.2+, Postgres)
    app/Services/Woo/  the connector: signed client, idempotent sync
    app/Console/       wgh:sync, wgh:fx
    database/          migrations for every table in the spec, milestone ladder
    tests/Feature/     the sprint 1 contract as tests
  docs/                the spec and the system map
```

---

## What sprint 1 does

The shop exposes one signed endpoint, `wghs/v1/export`. The dashboard pulls
orders, order items and attribution rows from it on a cursor, nightly and on
demand, and writes them into Postgres without ever duplicating a row.

That is the whole sprint, and it is deliberately small, because nothing above it
can be trusted until the truth source flows in cleanly and repeatably.

### The four properties that matter

**Idempotent.** `woo_order_id`, `woo_item_id` and `woo_attr_id` are unique, and
every row carries a `payload_hash` of exactly what the shop sent. A second sync
over unchanged data performs zero writes, not a no-op UPDATE that still moves
`synced_at`. That is what makes the acceptance test literal.

**Transactional per page.** A page of records commits or it does not.

**The cursor moves only on full success.** If page nine of twelve fails, the
cursor stays put and the next run re-pulls from there. Re-pulling costs nothing
because of the first property. Advancing early is how delta syncs lose rows.

**The cursor is inclusive and overlapped.** The shop returns rows at or after
the cursor, and the dashboard rewinds it two minutes on every run. An exclusive
cursor silently loses every row written in the same second as the last row of a
page.

### Two things worth knowing about the shop side

`wp_wghs_attribution` gained an `updated_at` column. Attribution rows are not
write-once: a pending WhatsApp tap becomes converted later, either automatically
when a matching gclid arrives on an order or by hand in WooCommerce >
Attribution. A cursor over `created_at` would pull that row once, while it was
still pending, and never again, so the dashboard would permanently report a sale
that happened as no sale. Every write in `inc/attribution.php` now goes through
`wghs_attr_insert()` / `wghs_attr_update()` so the stamp cannot be forgotten at
one call site, and it is explicit UTC, never MySQL's `CURRENT_TIMESTAMP`, which
follows the server session timezone.

Order lines are keyed on the WooCommerce **line item id**, not the product id.
Two lines of the same product in one basket are two rows, and keying on the
product would collapse them and halve the basket.

---

## Installing it on the server

Everything below runs on the shop server. Steps 1 and 2 are one-time.

### 1. Turn on the shop endpoint

Pull the theme and purge, then in wp-admin go to **Tools > WGH Dashboard
Access** and click **Generate secret**. Copy it.

Better, once it is working: put it in `wp-config.php` instead, which keeps it
out of the database and out of every database backup.

```php
define( 'WGHS_DASHBOARD_SECRET', 'the-64-character-value' );
```

The constant wins over the option, and the screen will say so.

### 2. Create the database

In cPanel, create a Postgres database and user, and note the credentials.

Namecheap only ships Postgres on some shared plans, and where it exists it is
10.23, which reached end of life in 2022. Laravel 12 still supports it, but it
has had no security patches for years. If cPanel shows no **PostgreSQL
Databases** icon, or you would rather not run an unpatched engine, use MariaDB
instead. The migrations use no Postgres-only column type, so the change is
`DB_CONNECTION=mysql` and `DB_PORT=3306` in `.env`.

One thing on the MariaDB path is handled for you and must not be removed: the
mysql connection pins its session timezone to `+00:00`. Laravel maps
`timestampTz` to a plain MySQL `TIMESTAMP`, and MySQL converts TIMESTAMP values
through the session timezone on both write and read. The server runs on US
Eastern time, so without the pin every historical timestamp shifts by an hour
when it rolls from EDT to EST in November, and revenue moves across day
boundaries between reporting periods. Postgres has no equivalent problem,
because `timestamptz` stores an absolute instant.

### 3. Install the application

```bash
cd ~/shop.websitesgh.com/wp-content/themes/websitesgh-shop/dashboard/api
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Then edit `.env`:

```
DB_CONNECTION=pgsql
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

WGH_SHOP_URL=https://shop.websitesgh.com
WGH_SHOP_SECRET=the-secret-from-step-1
```

```bash
php artisan migrate --force
php artisan db:seed --force        # seeds the milestone ladder
```

### 4. Prove it is talking to the shop

```bash
php artisan wgh:sync --dry-run
```

This writes nothing. It prints the shop's order and attribution totals beside
the dashboard's, so a broken sync is instantly distinguishable from an empty
shop. If it prints a 401, the message tells you the two things that cause it,
and the clock is the more common one.

### 5. Run the acceptance test

```bash
php artisan wgh:sync --verify
```

This is sprint 1's acceptance test from the spec. It syncs, fingerprints every
row, syncs again, and fingerprints again. It passes only when:

- the two fingerprints are identical
- the second run wrote zero rows
- the dashboard order count equals the shop's
- the dashboard attribution count equals the shop's
- no order item is orphaned

It also reports how many orders carry both a ref code and a click id, which is
the end-to-end proof that the WhatsApp loop is closed.

### 6. Schedule it

One cron entry in cPanel, every minute. Laravel's own scheduler decides what
actually runs, which is the nightly sync at 02:15 Accra time.

```
* * * * * cd ~/shop.websitesgh.com/wp-content/themes/websitesgh-shop/dashboard/api && php artisan schedule:run >> /dev/null 2>&1
```

Do not use WordPress's pseudo-cron for this. On a low traffic site it fires late
or not at all, which is already a known issue with the scheduled articles.

---

## Day to day

```bash
php artisan wgh:sync              # pull the delta
php artisan wgh:sync --full       # ignore the cursors, re-read everything
php artisan wgh:sync --dry-run    # totals only, writes nothing
php artisan wgh:sync --verify     # the acceptance test
php artisan wgh:fx 11.85          # today's GHS per USD
php artisan wgh:fx --list         # the recorded rates
```

Record an fx rate at least monthly. Ad spend is USD and sales are GHS, and every
comparison between them goes through a dated rate so a month closed in March
keeps March's rate when it is read again in September. Nothing converts until
there is at least one rate.

Logs are in `storage/logs/sync.log`.

---

## Running the tests

```bash
composer install                  # with dev dependencies
php artisan test
```

Twelve feature tests pin the failure modes the spec names, so a later sprint
cannot quietly reintroduce one: double import, a changed attribution row
creating a duplicate, a cursor advancing past a failure, two lines of one
product collapsing, a removed line lingering, two spellings of one phone number
producing two hashes, a local timestamp landing in a UTC column, and profit being
reported from partial costs.

---

## What is deliberately not here

**No live Google Ads API.** Decisions are exported as a Google Ads Editor change
file in sprint 5. Version B, settled.

**No profit until real costs are entered.** `profit_ghs` stays null until both
dealer cost and delivery cost are known. A half-costed order shows "awaiting
costs" rather than a flattering wrong number.

**Raw phone numbers never leave the server.** They are normalised to E.164 and
SHA-256 hashed at sync time. Only the hash goes into an export.

**Cancelled and failed orders are kept.** A cancelled order still consumed the
ad click that produced it, and hiding it would make cost per delivered order
look better than it is.
