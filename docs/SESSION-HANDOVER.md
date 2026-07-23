# Session handover: WebsitesGH Shop

Read this file in full before touching anything.

## Status
Sprint 0 complete. The repo exists and holds a clean, de-branded fork of the TechPlug
Aurora v2 theme with the websitesgh palette applied at token level. Nothing is deployed.
No WordPress install exists yet at shop.websitesgh.com.

## Verified facts (checked live, do not re-assume)
- websitesgh.com runs theme `websitesgh-v3` on LiteSpeed with full page cache hitting.
  Custom post types: `listing` plus ten DataHouse types. Seven custom REST namespaces:
  `wgh/v1`, `wgh-dh/v1`, `wgh-ad-slots/v1`, `wgh-chat/v1`, `geo/v1`, `lc-server/v1`,
  `kasqo/v1`. There is no WooCommerce and none is to be added.
- techpluggh.com runs theme `techplugghv2` with full WooCommerce, including `wc/v3`,
  `wc/store/v1`, `wc-analytics` and `wc/pos/v1/catalog`.
- The parent theme contains **zero** JSON-LD. No `schema.org`, no `application/ld+json`.
  WooCommerce core emits some Product schema on its own. Everything beyond that is to build.
- Live websitesgh fonts are **Figtree**, not Hanken Grotesk. Earlier notes were stale.

## Open decisions
- Hosting: confirm the host can take a second WordPress install on the subdomain.
- Confirm whether Adanse is to be installed here rather than rebuilding GEO from scratch.

## Next up
Sprint 1. See `docs/SPRINTS.md`.

## Hard rules
- Never add WooCommerce to websitesgh.com. It breaks the page cache the directory depends on.
- No em dash anywhere.
- Use "Accra International Airport", never the other name.
- Verify before claiming done. HTTP 200 plus slug match, or it is not done.
- Scrub the PAT from the git remote after every push.
