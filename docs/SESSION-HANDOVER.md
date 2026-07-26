> **STALE. Superseded by `/AGENT-HANDOVER.md` in the repo root.**
>
> This file describes Sprint 0, when nothing was deployed and no WordPress
> install existed. The shop has been live for weeks. Read AGENT-HANDOVER.md
> instead. This is kept only for the historical record of what was verified
> about websitesgh.com and techpluggh.com at fork time.

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

## Seeded catalogue
50 placeholder products across 7 categories, built by
`content-factory/engine/build_seed_catalogue.py`. Price range GHS 45 to 950.

**Every price is a placeholder** derived from observed Ghana asking prices, not a
confirmed dealer quote. Replace each one after the dealer calls. Prices are edited in
WordPress under Products, or in bulk from the Products list. The generator script never
needs to run again once the store is seeded.

Contact defaults are set to WhatsApp `233542148020` in two places: the Customizer
(`wghs_whatsapp`) and the gateway setting. Change either from the admin, no code needed.

## Open decisions
- Hosting: confirm the host can take a second WordPress install on the subdomain.
- Confirm whether Adanse is to be installed here rather than rebuilding GEO from scratch.

## Sprint status
Sprints 2 through 5 code complete. Theme is live on the subdomain (docroot is
~/shop.websitesgh.com, NOT under public_html). Schema layer (inc/schema.php)
owns Product, Offer, FAQPage, BreadcrumbList; an SEO plugin owns WebSite,
Organization, Article; WooCommerce core schema is disabled. llms.txt is served
by rewrite (flush permalinks once after pulling). Tracking (inc/tracking.php)
ships silent until GA4 and Google Ads IDs are set in Customize > Tracking.
Conversion engine in inc/conversion.php. Openverse image sideloader retuned
for appliances; run it from Tools > Setup to fetch licensed photos with saved
attribution. Two flagship articles ready in content-factory/articles.

## Attribution intelligence (inc/attribution.php + inc/tracking.php)
- Ref code loop: every WhatsApp tap gets a human reference (WG-XXXX) stamped
  into the prefilled message as "Order ref". The code the customer sends in
  chat is the code on the row. Admin has a Find ref box: paste, row appears,
  one click Sold. DB version 1.1 adds the ref column.
- On-site orders with a click id auto-convert (matching pending click or a
  fresh converted row). Owner only confirms pure-WhatsApp sales.
- Export button emits Google Ads offline conversion CSV (TimeZone=+0000),
  incremental, never uploads a row twice. Conversion name must match the
  import action in Google Ads (Customize > Tracking).
- Product intelligence strip: taps / sold / close rate per product, 30 days.
- Follow-up radar: pending rows 1 to 7 days old flagged; owner searches the
  ref in WhatsApp to revive the thread.
- Meta Pixel seeded (Customize > Tracking): PageView, ViewContent, AddToCart,
  InitiateCheckout, Purchase (refresh guarded), and Contact on WhatsApp taps.
  Set the ID at launch so retargeting audiences fill before any Meta spend.
- Future upgrade documented: WhatsApp Business API + Meta CAPI for Business
  Messaging (action_source business_messaging) when volume justifies it.

## Next up
Publish the two articles, set tracking IDs, place a live test order end to
end, then Sprint 6 launch tasks. Real product photos still beat everything.

## Hard rules
- Never add WooCommerce to websitesgh.com. It breaks the page cache the directory depends on.
- No em dash anywhere.
- Use "Accra International Airport", never the other name.
- Verify before claiming done. HTTP 200 plus slug match, or it is not done.
- Scrub the PAT from the git remote after every push.
