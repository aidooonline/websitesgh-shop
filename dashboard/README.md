# WGH Intelligence

The consolidated selling picture for shop.websitesgh.com. One question at every
level: what do I do next to sell more at profit.

The contract is `docs/ENGINEERING-SPEC.md`. The visual map is
`docs/system-overview.html`.

**Sprints 1, 2 and 3 are built.** The connector, the CSV ingest and join
engine, and the decision engine with the milestone ladder. Sprints 4 to 6
(React dashboard, owner input and exports, the Claude agent) are specced and
not started.

```
dashboard/
  api/                       Laravel application (PHP 8.2+, MariaDB or Postgres)
    app/Services/Woo/        the connector: signed client, idempotent sync
    app/Services/Ads/        CSV parsers, spend import, the join engine
    app/Services/Decisions/  verdicts, patterns, the milestone ladder
    app/Console/             wgh:sync, wgh:import, wgh:judge, wgh:fx
    database/                every table in the spec, milestone ladder seeded
    tests/Feature/           the sprint 1 to 3 contracts as tests
  docs/                      the spec, the system map, tracking templates
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

## The monthly loop

```bash
# 1. Record the rate, at least monthly. Nothing converts USD to GHS without it.
php artisan wgh:fx 11.85

# 2. Import each platform export. The platform is detected from the file.
php artisan wgh:import ~/exports/google-keywords.csv
php artisan wgh:import ~/exports/meta-campaigns.csv
php artisan wgh:import ~/exports/tiktok-promote.csv

# 3. Join it against sales and produce verdicts.
php artisan wgh:judge
```

`wgh:import --judge` does steps 2 and 3 in one go.

Re-importing the same file is safe and expected: it reports "already imported,
nothing changed". A figure that has been revised by the platform is treated as
a restatement and overwrites, never accumulates.

### What wgh:judge tells you

Four verdicts, and the difference between two of them is where the money is:

| | |
|---|---|
| **KEEP** | Cost per order is at or under profit per order. Scale it. |
| **WATCH** | Too early to judge. Says exactly how many days and clicks remain. |
| **FIX** | Clicks and WhatsApp taps but no sale, OR it sells at a loss. The page or the price is the fault, not the keyword. |
| **KILL** | Old enough AND spent enough AND sold nothing. Both conditions, always. |

FIX is the one that pays for the system. A keyword bringing people who open
WhatsApp and then do not buy looks identical, in a spreadsheet, to a keyword
bringing the wrong people entirely. The first is a page problem worth fixing
and the second is a targeting problem worth killing, and pausing the first
switches off demand you already paid to create.

Nothing is killed on thin data: it needs 14 days AND enough spend to have
risked one order's profit. Time alone would kill a keyword that spent forty
cents; spend alone would kill one that had two days.

Every verdict writes an immutable `decisions` row carrying the exact numbers
behind it. The model refuses to save a verdict with no evidence.

### Unmatched spend

Spend that matches no attribution row is reported near the top of the output,
never hidden in an "other" bucket. It means one of two things and both are
worth money: a tracking gap, where the money works but is invisible, or a real
leak, where clicks never reached a tap. Hiding it makes every cost per order
look better than it is.

The commonest cause by far is the Google Ads Final URL suffix not being set.
See `docs/TRACKING-TEMPLATES.md`.

### The profit line

`WGH_PROFIT_PER_ORDER_USD` in `.env` is the single most important number in the
configuration: it is the line between a keyword that earns and one that bleeds.
It starts at the spec's $8.75 estimate and **must** be replaced with the real
margin once dealer costs are known. Until then every verdict says so in its
evidence, and they are directionally right rather than exact.

## Getting advice without an API key

Sprint 6 will hand the consolidated picture to Claude automatically. Until then
the same loop runs by hand, and the payload is identical: only the way it
travels differs.

```bash
php artisan wgh:brief --export
```

That writes two files:

- **`wgh-briefing-<period>.md`** is the one to send. It is self-describing: it
  carries the goal, the constraints of this business, every number, the engine's
  verdicts, the patterns, the unmatched spend, the state of the conversion loop,
  and the exact template of the reply expected back. It can be handed to any
  analyst or any model with no covering note.
- **`wgh-data-<period>.csv`** is the same per-keyword and per-channel numbers
  flattened for a spreadsheet.

Send the markdown file. When the answer comes back:

```bash
php artisan wgh:brief --import=response.md
php artisan wgh:brief --show
```

The parser is deliberately forgiving about headings, because a response copied
out of a chat window arrives with its formatting mangled and rejecting good
advice over a stray character would end the habit. It is strict about one thing:
there must be a "Do this now" section. A briefing that does not end in a single
move is an essay, and storing one would leave a record that later reads as
though the system had nothing to say.

Every briefing is stored in `agent_briefings` with `model_used` recording where
it actually came from, so in six months it is still possible to ask who said
this and get a straight answer.

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
