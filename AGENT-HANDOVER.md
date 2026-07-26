# AGENT HANDOVER: WebsitesGH Shop

**Read this file in full before touching anything, and before asking the owner a
single question.** Most of what you would think to ask is answered here. He has
already spent a long time on this and repeating settled questions is the main
way an agent wastes his time.

Last updated: 26 July 2026.

### What is in this file

| | |
|---|---|
| 1 | The owner and the rules that never change |
| 2 | Repo, server, deploy, the two admin screens |
| 3 | Status at a glance |
| 4 | What is built |
| 5 | What is not built: the six dashboard sprints |
| 6 | Decisions already settled. Do not reopen. |
| 7 | Ten bugs and their root causes. Do not reintroduce. |
| 8 | The content and messaging position |
| 9 | The 50 products are placeholders |
| 10 | Pending owner actions |
| 11 | Do not ask him these |
| 12 | File map |
| 13 | Where to start |
| 14 | **How we actually work.** The loop, autonomy, reading his messages. |
| 15 | **Operational runbook.** Exact verify, commit, push, deploy commands. |
| 16 | **What you cannot do.** No server, no live site access. |
| 17 | Deliverable standards |
| 18 | His other projects. Do not touch. |
| 19 | Things that look like bugs but are not |

**If you read only three: 14, 15 and 16.** They are what stop you asking him
things you can answer yourself.

---

---

## 1. Who the owner is, and the rules that never change

**Stephen Aidoo.** Independent digital marketer and web developer, Accra, Ghana.
GitHub `aidooonline`. **He has no registered company. Never attribute one to him
in any file, byline, footer, schema field or deliverable.** If a brand or author
name is needed anywhere, ask him rather than inventing one.

He works terse and expects autonomous execution. He pushes back hard on shallow
work, and he is right to. Two things he has corrected more than once, so do not
be the third:

- **Verify before claiming.** Never say something is done, fixed or live without
  proving it. Lint it, build it, grep the compiled output, confirm the push.
- **Trace the root cause.** Do not patch a symptom. Every bug in section 7 was
  found by reading the actual code path, and several were made worse first by
  guessing.

### Standing rules, non-negotiable

- **No em dash (U+2014) anywhere.** Not in code, content, comments or commit
  messages. Check before every commit.
- **Commits are authored as `Stephen Aidoo <aidooonline@gmail.com>`.** The code
  is his. Never `claude@anthropic.com` or any bot address. Set it every session:
  `git config user.email "aidooonline@gmail.com"` and
  `git config user.name "Stephen Aidoo"`.
- **Scrub the PAT from the git remote after every push.** Set it, push, reset the
  remote to the plain https URL.
- **"Accra International Airport", never "Kotoka."**
- **Never guess Pexels photo IDs.** Ask him for URLs.

---

## 2. Where everything lives

| Thing | Value |
|---|---|
| Repo | `github.com/aidooonline/websitesgh-shop` (**public** since 25 July) |
| Site | `shop.websitesgh.com` |
| Server | Namecheap shared cPanel, PHP 8.3, WordPress 7.0.2 |
| Theme path | `~/shop.websitesgh.com/wp-content/themes/websitesgh-shop` |
| Forked from | `aidooonline/techplugghv2` (Aurora v2), fully de-branded |
| WhatsApp / MoMo | `0542148020`, international `233542148020` |
| SEO plugin | The SEO Framework |
| Cache | LiteSpeed |

### Deploy

The repo is public, so **pulls need no token**:

```bash
cd ~/shop.websitesgh.com/wp-content/themes/websitesgh-shop
git pull https://github.com/aidooonline/websitesgh-shop.git main
```

Then **LiteSpeed Purge All**. Pushes still need the PAT; scrub it afterwards.

### Two admin screens you will use constantly

- **Appearance > Shop Diagnostics.** Shows live state with actual values
  (permalinks, each page, `show_on_front`, `page_for_posts`, each menu with its
  id and item count, WhatsApp number, cart type, asset version, products missing
  images). **Run this before diagnosing anything.**
- **Its Repair button** creates missing pages, fixes the Reading settings,
  rebuilds and assigns all four menus, publishes and dates the articles, trashes
  Hello world, flushes permalinks. Seconds, no timeouts. The full setup at
  **Tools > WebsitesGH Shop Setup** also seeds products and images and is slower.

---

## 3. Status at a glance

| Workstream | State |
|---|---|
| Shop build | **Live and functionally complete.** Polish only. |
| Content | **20 articles.** 5 live, 15 scheduled at 3-day intervals to 8 Sept. |
| Marketing assets | **Built, not launched.** Nothing is spending. |
| WGH Intelligence dashboard | **Fully specced, zero code written.** Sprints 1 to 6. |

---

## 4. What is built

### The shop
Custom theme, light palette (green `#0E8C5A`, gold `#E2A013`, Figtree). 14
commercial pages, 7 product categories, blog with sticky rail, GEO schema
(Product, Offer, FAQ, Breadcrumb, llms.txt), designed About page built from
inline SVG diagrams.

### The order path, cart-first
Product page **"Get it now"** adds to cart and goes to the cart. Grid cards and
the mobile bar do the same. The cart carries **"Send order on WhatsApp"** which
sends the whole basket in one formatted message. **There is no checkout in the
flow**: any hit on `/checkout/` redirects to the cart, except `order-received`,
`order-pay`, and a deliberate `?form=1` for the rare non-WhatsApp buyer.

After the message opens, the cart empties itself and the buyer lands on a
confirmation. **WhatsApp opens first, always**, or the message could be rebuilt
from an empty basket.

### Attribution engine
Custom `{prefix}wghs_attribution` table. Captures gclid/gbraid/wbraid and UTMs in
90-day first-party cookies. Logs add-to-cart as a funnel stage and every WhatsApp
tap. Stamps a **WG-XXXX ref code** into each message, so the code the customer
sends is the row in the admin. Captures name, phone and area from a soft popup
before the first tap and remembers it. Admin at **WooCommerce > Attribution**,
with a Google Ads offline-conversion CSV export.

### Content
20 articles in `inc/setup-data/articles.json`, each with a `days_offset` that
backdates or schedules it. Two are living data assets meant to be refreshed
monthly: the **Ghana Appliance Price Index** and the **Running Costs** table.

### Marketing assets, in `marketing/`
- `PLAYBOOK-2026.html`, the multi-channel playbook
- `google-ads/keywords-editor-import-v3.csv`, 689 keywords, 20 ad groups, tiered
- `google-ads/negative-keywords.csv`, 107
- `google-ads/responsive-search-ads.csv`, 266 rows
- `google-ads/FORECAST-FINDINGS.md`, the forecast that reset the plan
- `content-factory/CONTENT-STRATEGY.md`, the cluster and cadence plan

---

## 5. What is NOT built: WGH Intelligence

**Six sprints, no code.** The full engineering spec is
`dashboard/docs/ENGINEERING-SPEC.md` and it is the contract: every sprint has a
selling purpose, exact scope, the failure modes to design against, and an
acceptance test that must pass **on real data** before the sprint is done.
`dashboard/docs/system-overview.html` is the visual map.

Laravel API + Postgres + React, code inside this repo under `/dashboard`, hosted
on the same server.

1. **Foundation + WooCommerce connector.** Signed REST pull, cursor delta,
   idempotent.
2. **CSV ingest + join engine.** Per-platform parsers, spend to tap to order to
   profit, unmatched spend surfaced not hidden.
3. **Decision engine.** Keep / Watch / Fix / Kill on keywords, products,
   channels, creatives. Plus the milestone gate ladder.
4. **React dashboard.** Macro, meso, micro. Server-side aggregation.
5. **Owner input, profit truth, Enhanced Conversions export** (gclid + SHA-256
   hashed phone), weekly export reminder.
6. **Claude selling agent.** Claude for reasoning, fal.ai for bulk. Balanced
   personality. Fires on data import. Advises, never acts.

**This was blocked on keyword volumes. The forecast came back, so it is
unblocked. Sprint 1 is the next thing to build.**

---

## 6. Decisions already settled. Do not reopen these.

- **Search, not Performance Max or Shopping.** The sale happens on WhatsApp, so
  Google cannot read a purchase. PMax would optimise on nothing.
- **Meta Click-to-WhatsApp is the primary paid channel.** The forecast proved
  Google Search can only spend about $9.55 a month on the v1 keyword set, which
  is 98% of the budget unspendable. Google is a cheap profitable trickle, not the
  growth engine. **Do not build plans that assume Search scale.**
- **TikTok goes through Promote, not Ads Manager.** Ads Manager enforces roughly
  a $500 minimum campaign and $50/day, about $1,500 a month, which is out of
  reach. Promote has no minimum.
- **Version B for the dashboard action layer.** Decisions are made in the
  dashboard and exported as a Google Ads Editor change file. **No live Google Ads
  API.** Too fragile on shared hosting, and it hands software control of his
  money before it has earned trust.
- **Cart-first, not one-tap.** The cart is a measurable middle step and it builds
  a retargeting pool of people who added but did not message.
- **Content leads with desire, not utility.** See section 8.
- **No featured or lead post on the blog listing.** He asked for it removed. Every
  post renders in the same river layout. Do not reintroduce a hero post.

---

## 7. Bugs already fixed, and their root causes. Do not reintroduce these.

Each of these cost real time. They are listed with the mechanism so you recognise
the class of problem, not just the instance.

1. **`esc_url()` strips `%0A`.** Escaping a finished WhatsApp link a second time
   destroys every newline and the order arrives as one run-on blob with literal
   asterisks. **Always use `wghs_wa_href()` in templates. Never
   `esc_url( wghs_wa_link( ... ) )`.**
2. **CSS class collision.** `inc/illustrations.php` emitted
   `<span class="wghs-art">` for SVG placeholders while `single.php` used
   `<article class="wghs-art">`. The illustration rule
   (`flex; items-center; justify-center`) turned the whole article into a centred
   flex row. The illustration wrapper is now `.wghs-ill`. **Check for collisions
   before naming a class.**
3. **Static asset version.** Everything was enqueued with a hardcoded
   `WGHS_VERSION` that never changed, so browsers and LiteSpeed served the first
   `main.css` they ever saw and **every CSS fix was invisible for weeks**. Assets
   now version by `filemtime` via `wghs_asset_ver()`. **Never hardcode an asset
   version.**
4. **`wp_set_post_terms()` with a term name silently does nothing** for
   hierarchical taxonomies like `category`. It needs term IDs. This is why seeded
   articles all landed in Uncategorized.
5. **`page_for_posts` is ignored unless `show_on_front` is `'page'`** with a
   `page_on_front` set. Without it the Guides page renders empty and the blog
   looks like it does not exist.
6. **The setup screen is under Tools**, registered with `add_management_page()`,
   not Appearance. A link to `themes.php?page=wghs-setup` returns "not allowed".
7. **`wghs_wa_number()` needs its hardcoded fallback.** `wghs_opt()` returns an
   empty string when the Customizer field is blank, and an empty number made the
   cart button silently fall back to WooCommerce's checkout button.
8. **The lead-capture popup must log the tap itself.** `window.open()` fires no
   click event, so the attribution beacon never ran again after the popup
   collected details, and the first order from every customer saved empty
   customer fields. The beacon now defers any tap the popup will intercept, and
   the popup calls `window.wghsLogTap()` explicitly.
9. **`stamp()` must fill the blank fields, not prepend.** It checked for the
   absence of `Name:`, but the message template already contains it, so the
   buyer's details were never written into the WhatsApp text.
10. **Never `esc_url` a raw image URL into a WhatsApp message expecting a
    preview.** LiteSpeed serves WebP to crawlers, so a `.jpg` link renders no
    thumbnail. Lead with the **product page URL** and let `og:image` do it.

---

## 8. The content and messaging position

He corrected this twice, so hold it firmly.

**Do not lead with electricity or running costs.** Nobody buys a blender to save
six cedis a month on ECG. It makes the shop look like it thinks its customers are
counting pesewas, and it says nothing about whether the product is good. The
running costs page still exists as a reference and a citable data asset, and a
cost note appears on hot plates only, where it reaches about GHS 92 a month and
genuinely changes a decision.

**Lead with, in this order:** the moment and the buyer, then the curious
mechanism nobody told them, then the proof, then the order path.

The things that actually decide a purchase here:
- **Labour and time.** A blender replaces forty minutes of grinding, daily.
- **Income.** These are tools of trade. A blender is how a shito seller makes
  stock. Content written to the earner outsells content written to the consumer.
- **Relief from a specific misery.** Light off at 2am with children who cannot
  sleep.
- **Life stage.** First apartment, marriage, a baby. People buy five things, not
  one. Highest basket value in the business.
- **Fear of being cheated.** Gates every other motive, which is why pay on
  delivery is a selling argument and not just logistics.

The moat is **first-hand Ghana-specific measured data**: real dealer prices dated
to the month, measured runtimes, real capacities, local conditions. Generic
appliance content is being commoditised by generative models. Ours cannot be
written from outside Accra.

---

## 9. The 50 products are placeholders. This matters.

`inc/setup-data/products.json` holds 50 products across 7 categories, generated
by `content-factory/engine/build_seed_catalogue.py`. The generator says it
plainly and so does this file:

> **Every price is a placeholder derived from observed Ghana asking prices, not a
> confirmed dealer quote. No supplier has confirmed any of these.**

The product names are realistic for the Ghana market and the categories are
right, but **nothing here is confirmed stock at a confirmed price**. Stephen is
calling suppliers. Until those calls happen:

- Do not quote these prices to anyone as real.
- Do not build margin models on them without saying they are estimates.
- Expect the catalogue to be replaced or heavily edited.

Prices are editable in WordPress under Products, so the JSON never needs editing
again once seeded. When the dealer calls are done, update prices in WP, then
update the **Price Index** article, which is the citable asset.

---

## 10. Pending owner actions. Do not build around these being done.

1. **Call suppliers**, confirm real products, real prices, real availability.
2. **Set featured images on products.** Diagnostics showed 42 missing. This is
   what WhatsApp previews, so it directly affects orders.
3. **Run the v3 keyword list through Keyword Planner** and send the forecast
   back. This tells us whether the ceiling lifts above $9.55 a month.
4. **Set tracking IDs** in Customize > Tracking: GA4, Google Ads, Meta Pixel.
5. **Create a Google Ads offline conversion action named exactly
   `WhatsApp Sale`.**
6. **Untick "Discourage search engines"** on launch day.
7. **Shoot product photos and the 10 videos** from the shot list.

---

## 11. Do not ask him these

He has answered them already and asking again wastes his time:

- What his company is called. **He has none.**
- Whether to use PMax or Shopping. **No. Search only.**
- Whether to use the Google Ads API. **No. Version B, export a change file.**
- Whether to put a featured post on the blog. **No.**
- Whether the products are real. **They are placeholders.**
- What currency ads are in. **USD.** Only shop prices are GHS.
- Whether to add a checkout. **No. WhatsApp is the checkout.**
- What his WhatsApp number is. **233542148020.**

---

## 12. File map

```
functions.php                       theme bootstrap, asset versioning
inc/
  attribution.php                   the attribution table, beacon, admin, export
  lead-capture.php                  soft popup for name, phone, area
  express-order.php                 cart WhatsApp button, message builder,
                                    checkout redirect, bundle handler, clear cart
  conversion.php                    order bar, at-a-glance panel, trust strip
  whatsapp-product.php              product message template, Open Graph
  template-tags.php                 wghs_wa_number, wghs_wa_link, wghs_wa_href
  schema.php                        JSON-LD and llms.txt
  setup.php                         full seeder, Tools > WebsitesGH Shop Setup
  diagnostics.php                   Appearance > Shop Diagnostics, Repair button
  setup-data/                       products, categories, pages, articles JSON
single.php  index.php  sidebar.php  blog detail, listing, sticky rail
page-about.php                      designed About, inline SVG
marketing/                          playbook, keywords, forecast findings
content-factory/                    content strategy, writing standard, GEO layer
dashboard/docs/                     ENGINEERING-SPEC.md, system-overview.html
docs/                               older sprint and conversion notes
```

---

## 13. Where to start

Read `dashboard/docs/ENGINEERING-SPEC.md`, then build **Sprint 1**: Laravel,
Postgres, the full data model, and the WooCommerce connector. Its acceptance test
is that a real order from the live shop appears in the dashboard database with
its ref code and click id, and that running the sync twice changes zero rows.

Do not skip the acceptance tests. They are the reason the spec exists.

---

## 14. How we actually work. This is the part that stops the questions.

### The loop

You do not have server access. Stephen deploys. Every change goes:

1. **You edit, build, verify, commit and push.** Never ask permission first.
2. **You give him the pull command** at the end of the message. He runs it and
   hits LiteSpeed Purge All.
3. **He sends a screenshot**, usually with red circles or arrows drawn on it, and
   a short line about what is wrong.
4. **You diagnose from the screenshot and the code**, not from the live site,
   because you cannot reach it.

That is the whole rhythm. Do not break it by waiting for approval between steps.

### He expects autonomous execution

Do not ask "shall I do X?" for anything inside the brief. Do it, then tell him
what you did and why. He will correct you if it is wrong, and he corrects fast.

**Push directly to `main`.** No branches, no pull requests, unless he says so.

The only things worth asking about are genuine forks in the road where the wrong
choice costs real money or is hard to reverse, and even then, give him a
recommendation with reasons rather than an open question.

### Reading his messages

- **He is terse and often uses voice input.** Expect transcription artefacts.
  "80$ a day" may mean 80 dollars a month. "constipation" meant "computation".
  Read for intent, and if a number looks absurd, address both readings rather
  than picking one and being wrong.
- **When he pushes back, he is usually right.** He has caught real errors
  repeatedly: the keyword volume assumption, the electricity over-emphasis, the
  missing articles. Do not defend. Check, admit plainly, fix.
- **"still not working" means test differently, not repeat the fix.** Twice the
  real cause was deployment or caching rather than code. Check the Diagnostics
  page and the asset version before assuming your fix was wrong.
- **He notices when you claim without verifying.** Never write "this is fixed"
  unless you have grepped the compiled output or run the check.

---

## 15. Operational runbook

### Before every push, run all of this

```bash
cd /path/to/repo

# 1. PHP lint every file
find . -name "*.php" -not -path "./.git/*" -not -path "./node_modules/*" > /tmp/p.txt
while read f; do php -l "$f" | grep -q "No syntax errors" || echo "FAIL $f"; done < /tmp/p.txt

# 2. Rebuild CSS. MANDATORY after touching assets/css/tailwind.css.
npm run build

# 3. Prove the CSS actually compiled. Do not trust the build output alone.
grep -o "\.your-new-class{[^}]*}" assets/css/main.css

# 4. Em dash check across everything
python3 -c "
import glob
bad=[f for f in glob.glob('**/*.*', recursive=True)
     if '.git' not in f and 'node_modules' not in f
     and f.rsplit('.',1)[-1] in ('php','css','js','md','json')
     and '\u2014' in open(f, encoding='utf-8', errors='ignore').read()]
print('clean' if not bad else f'EM DASH in {bad}')"

# 5. JSON validity if you touched seed data
python3 -c "import json; json.load(open('inc/setup-data/articles.json'))"
```

### Commit and push

```bash
export GH_PAT='<his token, in memory, expires ~Aug 2026>'
git config user.email "aidooonline@gmail.com"
git config user.name "Stephen Aidoo"
git add -A
git commit -q -m "Short subject line

Body explaining WHY, not just what. He reads these."
git remote set-url origin "https://aidooonline:${GH_PAT}@github.com/aidooonline/websitesgh-shop.git"
git push -q origin main
git remote set-url origin https://github.com/aidooonline/websitesgh-shop.git   # scrub

# Confirm it actually landed
git fetch -q origin main
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] && echo "PUSH CONFIRMED"
```

**Commit messages are long and explain reasoning.** He reads them and they are
part of the project record. Say what was wrong, what the root cause was, and why
the fix is the right one.

### The pull command to give him, every time

```bash
cd ~/shop.websitesgh.com/wp-content/themes/websitesgh-shop
git pull https://github.com/aidooonline/websitesgh-shop.git main
```

Then tell him to **Purge All**. If you changed anything that the Repair button
handles (pages, menus, article dates, categories), tell him to run
**Appearance > Shop Diagnostics > Repair** as well.

---

## 16. What you cannot do. Know this before you promise anything.

- **No SSH, no server access, no database access.** Stephen runs every command on
  the server.
- **You cannot load `shop.websitesgh.com`.** It is not in the sandbox network
  allowlist, so `curl` and `web_fetch` return nothing. You cannot inspect the
  live HTML or check whether a fix landed. **Diagnose from the code and from his
  screenshots.** If you genuinely need live data, ask him to run the Diagnostics
  page and send it, or to view source and read you a specific line.
- **You cannot see the rendered result of your own CSS.** Verify by grepping
  `assets/css/main.css` for the compiled rule and reasoning about it.
- **WordPress admin actions are his.** Running setup, purging cache, setting
  featured images, assigning menus. Build the tooling, tell him which button.

Allowlisted domains that do work: `github.com`, `raw.githubusercontent.com`,
`api.github.com`, `websitesgh.com`, `*.imaanihomes.com`, `techpluggh.com`,
`*.gra.gov.gh`, `*.bog.gov.gh`, npm and pypi.

---

## 17. Deliverable standards

**Marketing and strategy documents are HTML, built to the Iridak playbook
standard.** He has used this across projects and expects it:

- Warm off-white background `#f8f6f2`, ink `#20211C`, orange accent `#e8630a`
- Bebas Neue for display, DM Sans for body, DM Mono for labels and meta
- Full-height cover page, then a table of contents, then numbered sections
- Tables for anything comparative, badge pills for match types and tiers
- Callout cards for decisions and warnings
- Delivered as a downloadable file, not pasted into chat

**Where files go:** build in the working directory, copy final deliverables to
`/mnt/user-data/outputs/`, then call `present_files` so he can download them.
Share files, not folders. No long explanation after the link.

**Code files** are committed to the repo, not presented as downloads.

---

## 18. His other projects. Do not touch them.

Stephen runs several repos in parallel. If he mentions one, it is context, not an
instruction to go and change it:

- `aidooonline/websitesgh-v3` and `websitesgh-datahouse`, the main websitesgh.com
  platform
- `aidooonline/regalia-theme`, the Imaani Homes Regalia relaunch
- `aidooonline/leadcapture`, the multi-tenant lead system
- `aidooonline/kasqoreels-api` and `kasqoreels-app`, Kasqo
- `aidooonline/techplugghv2`, TechPlug GH, which this theme was forked from

Each has its own handover file and its own rules. **Stay in this repo unless he
explicitly moves you.**

---

## 19. Things that look like bugs but are not

- **`/companies-in-ghan/` and `/expore/`** on websitesgh.com are intentional SEO
  slugs. Never "fix" them. They do not appear in this repo but he may mention
  them.
- **The cart page must be the classic shortcode**, not the block cart. The
  WhatsApp button hooks `woocommerce_proceed_to_checkout`, which block carts do
  not fire. Setup forces this; do not undo it.
- **`aria-hidden="true"` on the article media links** in `index.php` is
  deliberate. The title link is the real one and duplicating it for screen
  readers is noise.
- **Scheduled posts sitting past their date** are a wp-cron issue on a low-traffic
  site, not a code bug. A real server cron fixes it.
