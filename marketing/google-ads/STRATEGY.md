# WebsitesGH Shop: Google Ads strategy 2026

## The core decision: Search, not PMax or Shopping

Standard ecommerce runs Shopping + Performance Max. You should NOT, at least
not at launch, and the reason is structural, not preference:

- Shopping and PMax need on-site checkout with a product feed and a purchase
  conversion Google can read. Your orders happen on WhatsApp. Google cannot
  see a WhatsApp sale, so PMax and Shopping would optimise on nothing and burn
  budget. (2026 research: PMax is built for retailers with 50+ products and a
  clean feed with purchase values; without readable purchase data it
  underdelivers.)
- Your real conversion is a WhatsApp tap = a LEAD. For lead generation, every
  2024-2026 dataset shows Search beats PMax on quality, because buyers use
  specific terms that need specific ad copy.

So: Search campaign, manual-ish control, WhatsApp tap as the conversion.
Revisit PMax ONLY after checkout is a real path with tracked purchases, or
after Search is profitable and you want cross-channel scale.

## Account structure

Campaign: "WGH Shop - Search - Appliances"
- Location: Greater Accra first (tight), expand to Tema, Kumasi, Takoradi once
  proven. Nationwide only after the delivery promise holds there.
- Language: English
- Network: Search only. Turn OFF Search Partners and the Display Network at
  launch (they leak budget on low-intent placements).
- Bidding: START on Maximise Clicks with a max CPC cap of GHS 2.50 for the
  first 2 weeks to gather data cheaply. Once you have ~15-20 conversions
  logged (WhatsApp taps via the tracking we built), SWITCH to Maximise
  Conversions or Target CPA.
- Budget: GHS 150-170/day (about GHS 5,000/month).
- Conversion: import the offline WhatsApp conversion we built, plus the
  on-site WhatsApp-tap event. Bid toward the tap now; upgrade to the offline
  SALE import once you have volume, so Google learns buyers not tappers.

## Ad groups (14, one theme each, each mapped to a landing page)

Blenders, Kettles, Microwaves, Rice Cookers, Hot Plates, Power Banks, Earbuds,
Bluetooth Speakers, Irons, Fans, Rechargeable Lamps, School Bags, Hair
Clippers, Hair Dryers.

Each ad group is tight (one product theme) so the keyword, the ad copy and the
landing page all match. That match is what raises Quality Score, which is what
lowers your real CPC. A high Quality Score can cut CPC by up to half, so tight
structure is not tidiness, it is the single biggest lever on cost.

## Match type strategy

- EXACT match on the core term (e.g. [blender]) captures the precise buyer.
- PHRASE match on the buy-intent expansions ("blender price in ghana",
  "power bank accra") captures the money queries with a little flexibility.
- NO broad match at launch. Broad match without a strong conversion signal is
  how budgets die. Add broad ONLY later, guided by Smart Bidding, once the
  conversion data is solid.
- 55 negative keywords loaded from day one (free, used, tokunbo, repair, jobs,
  competitor names, info-seekers). This is the moat against wasted spend.

## The keyword files (ready to import in Google Ads Editor)

1. keywords-editor-import.csv    - 621 keywords, 14 ad groups, phrase+exact,
   max CPC and final URL per row
2. negative-keywords.csv         - 55 campaign negatives
3. responsive-search-ads.csv     - 15 headlines + 4 descriptions per ad group,
   Ghana hooks (pay on delivery, same day Accra, check before you pay)

### How to import in Google Ads Editor
1. Open Editor, download your (empty) account
2. Account > Import > from file > keywords-editor-import.csv
3. Repeat import for negative-keywords.csv and responsive-search-ads.csv
4. Review the proposed changes, set the campaign budget and location, Post
5. Nothing spends until you set the campaign live

## Extensions (add these, they lift CTR for free)

- Sitelinks: Price Index, How to Order, Delivery Areas, WhatsApp Us
- Callouts: Pay On Delivery, Same Day Accra, Genuine & New, Check Before You Pay
- Structured snippets: Brands / product types you carry
- Call extension: your line, so mobile users tap to call
- Location: if you have a pickup point

## Bidding phases (the money discipline)

Phase 1, weeks 1-2: Max Clicks, CPC cap GHS 2.50, gather cheap data.
Phase 2, weeks 3-4: switch to Max Conversions once ~15-20 taps logged.
Phase 3, month 2+: Target CPA once the offline SALE import has volume, so
Google optimises for paying customers, not tappers. Target CPA start point:
your profit per order (~GHS 105) minus your desired margin, so maybe GHS 60-70
target cost per sale.

## KPI benchmarks to hold yourself to (Ghana, this niche)

- CPC: GHS 1.50-3.00 (Search, ecommerce is the cheapest vertical globally)
- CTR: aim 4-6%+ on tight ad groups
- Tap rate (landing to WhatsApp): 8-15% on a good product page
- Tap-to-sale: 30-50% (WhatsApp closes well in Ghana)
- Cost per DELIVERED order: keep under GHS 80 (against ~GHS 105 profit)
- If any ad group's cost-per-order exceeds profit-per-order for 2 weeks, pause
  it and move budget to the winners. The Attribution dashboard shows this.
