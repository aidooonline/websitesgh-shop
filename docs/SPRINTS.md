# WebsitesGH Shop sprint plan

Goal: a clean, fast, pay on delivery storefront that ranks organically, converts paid
traffic, and gets cited by answer engines. Ship in order. Do not start a sprint until
the one before it is verified live.

---

## Sprint 0. Fork and rebase  [DONE, commit dbac293]
Fork techplugghv2, strip client data, rebase palette onto websitesgh tokens.

## Sprint 1. Infrastructure  [BLOCKED on host confirmation]
1. Create the `shop.websitesgh.com` subdomain and a second WordPress install.
2. Install WooCommerce. Currency GHS. Store address Accra. Weight kg.
3. Enable Cash on Delivery and the bundled Order via WhatsApp gateway.
4. Wire cPanel git deployment to `.cpanel.yml`. Deploy the theme, activate it.
5. LiteSpeed cache on, with cart, checkout and my-account excluded from page cache.

**Exit test:** `curl -I https://shop.websitesgh.com/` returns 200 and
`/wp-json/` lists `wc/v3`.

## Sprint 2. Light mode rebuild
The parent theme is dark. Hue swapping is not enough, contrast has to be rebuilt.
1. Header, footer, nav, mobile menu on white with `#EDEAE0` rules.
2. Product card, archive grid, single product, cart, checkout.
3. Home sections: hero, featured, trust, promo, cta. Drop the glow and shimmer
   effects, they belong to the dark theme.
4. Kill unused Tailwind output. Target Lighthouse mobile performance 90 or better.

**Exit test:** every page passes WCAG AA contrast, no dark background survives,
Lighthouse mobile 90+.

## Sprint 3. Catalogue and ordering
1. Seed real products from confirmed dealer prices. Every product needs a real photo,
   a real GHS price and real stock status.
2. Checkout reduced to name, phone, location, product. Nothing else.
3. WhatsApp handoff verified end to end: order records in WooCommerce first, then opens
   WhatsApp with the summary.
4. Order confirmation page and SMS or WhatsApp confirmation to the buyer.

**Exit test:** place a live test order, confirm it appears in WooCommerce and the
WhatsApp message contains the order number, items and total.

## Sprint 4. GEO layer
The parent theme ships zero JSON-LD. Everything here is new build. Install Adanse
(`aidooonline/adanse`) rather than rebuilding what already exists.
1. Schema on every relevant template: Product with Offer, price, availability and
   priceValidUntil, BreadcrumbList, Organization with Accra NAP and sameAs, FAQPage,
   ItemList on archives.
2. Quick Answer capsule in the first 60 words of every money page: product, price,
   key numbers, verdict. This is the block that gets lifted into AI Overviews.
3. Fact density. Specific numbers and dated prices beat adjectives. Every price
   carries a visible "verified [month year]" stamp.
4. Engineered Q and A block using the literal prompts buyers type, each answer
   self contained and liftable, mirrored into FAQ schema.
5. `/llms.txt` at the domain root pointing crawlers at the money pages.
6. The price index page: every product, live GHS price, dated, refreshed monthly.
   This is the strongest citation magnet in the plan.

**Exit test:** Rich Results Test passes for Product and FAQ, `/llms.txt` returns 200,
and the price index page renders current dates.

## Sprint 5. Tracking and conversions
1. Google Site Kit. GA4 and Search Console on the subdomain as its own property.
2. Google Ads conversion firing on `order-received`, with order value and currency.
3. GA4 ecommerce events: view_item, add_to_cart, begin_checkout, purchase.
4. Meta and TikTok pixels for the organic to paid retargeting loop.
5. Server side order value reconciliation so reported revenue matches WooCommerce.

**Exit test:** place a test order, confirm the conversion registers in Google Ads with
the correct value within 24 hours.

## Sprint 6. Launch
1. Product videos from Kasqo, one per SKU, 15 seconds, vertical.
2. Organic push: TikTok, Reels, WhatsApp Status, Jiji listings linking back.
3. Google Ads using the Iridak playbook standard.
4. Internal links from websitesgh.com listings and DataHouse pages into the shop.

---

## Blockers, in priority order
1. Host confirmation that a second WordPress install on the subdomain is possible.
2. The WhatsApp number for the gateway.
3. Confirmed dealer prices. Nothing in Sprint 3 can start without real numbers.
