# WebsitesGH Shop

**New agent or new session? Read [`AGENT-HANDOVER.md`](AGENT-HANDOVER.md) in full
before touching anything.** It carries the current state, the standing rules, the
decisions already settled, the root cause of every bug already fixed, and the
questions not to ask the owner.

Second: [`dashboard/docs/ENGINEERING-SPEC.md`](dashboard/docs/ENGINEERING-SPEC.md),
the six-sprint contract for the intelligence dashboard, which is the next thing
to build.

---

---

## Starting a new chat or session

A fresh agent does not know which repo this is. Paste this to start:

```
Project: WebsitesGH Shop, a WhatsApp-first WooCommerce store at
shop.websitesgh.com. Repo: https://github.com/aidooonline/websitesgh-shop
(public, clone or fetch raw files directly).

Read AGENT-HANDOVER.md in the repo root IN FULL before doing anything or
asking me any questions. Then read dashboard/docs/ENGINEERING-SPEC.md.

Task: <what you want done>
```

Common tasks to put on the last line:

- `build Sprint 1 of the WGH Intelligence dashboard` (the next thing to build)
- `write the next batch of articles, continuing the calendar in inc/setup-data/articles.json`
- `finalise the Google Ads campaign, forecast attached`
- `fix <problem>. Run Appearance > Shop Diagnostics first and work from what it reports`

WooCommerce theme for **shop.websitesgh.com**. Forked from `aidooonline/techplugghv2`
(Aurora v2) and restyled onto the websitesgh green and gold system.

Model: pay on delivery. The customer orders on site, the order is recorded in
WooCommerce, the dealer is called, the dealer delivers, the customer pays the rider.
No payment gateway is required to launch.

## What carried over from the fork
- WooCommerce integration and template overrides (`inc/woocommerce.php`, `woocommerce/`)
- Order via WhatsApp gateway (`inc/whatsapp-gateway.php`) which records the order in
  WooCommerce first, then hands off to WhatsApp with a prefilled summary
- One click setup and seeding system (`inc/setup.php` + `inc/setup-data/*.json`)
- Content factory publish engine (`content-factory/engine/*.py`)
- Tailwind build pipeline and cPanel git deployment

## What was stripped
All TechPlug client data: laptop articles, supplier product JSON, category and product
imagery, and the client Google Ads plan. Seed files are now empty scaffolds.

## Design tokens
Palette and type are copied verbatim from the live websitesgh.com `:root` block so the
shop and the directory read as one brand. See `tailwind.config.js`. Do not invent hues.

| Token | Value |
|---|---|
| green | `#0E8C5A` |
| green deep | `#0A6E47` |
| green pale | `#E9F7F0` |
| gold | `#E2A013` |
| gold pale | `#FBEFD2` |
| ink | `#14201A` |
| line | `#EDEAE0` |
| font | Figtree |

Note: the parent theme is dark. This one is light (`bg #FFFFFF`). Any template that
still assumes a dark background needs its contrast checked, not just its hue swapped.

## Build
```
npm install && npm run build    # Tailwind -> assets/css/main.css
```

## Deploy
cPanel git deployment via `.cpanel.yml`, rsync to
`$HOME/public_html/shop.websitesgh.com/wp-content/themes/websitesgh-shop`.
After deploy, run LiteSpeed Purge All.

## Rules
- No em dash anywhere in code, content or comments.
- Nothing is done until it is verified live (HTTP 200 plus slug match).
- Scrub the PAT from the git remote after every push.
- Commits are authored as Stephen Aidoo, aidooonline@gmail.com.

Read `docs/SESSION-HANDOVER.md` first in any new chat.
