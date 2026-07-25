# WebsitesGH Shop - Article Writing Standard (LOCKED)

Every article publishes to this standard or it does not publish. This is the quality bar that
makes the batch "severely researched" rather than generic filler.

## Structure (built for SEO + AI Overview / GEO)
- **Direct answer first.** The first 40 to 60 words answer the query plainly and include the
  primary keyword and a price/spec fact. This is the block AI answer engines lift and cite.
- **H2s mirror real questions.** Use the People Also Ask and AI Overview questions captured in
  the research file as the H2 structure. Extractable, scannable, question-shaped.
- **A table where it helps.** Spec tables on model pages; comparison tables on "vs" articles;
  a price table on price/guide articles. Tables win featured snippets and AI citations.
- **Bottom line block.** A short summary near the end that restates the answer and the CTA.

## Length
- Guides and comparisons: 1,200 to 2,000 words.
- Price and category money pages: 900 to 1,400 words.

## Schema (via the site SEO plugin or inline JSON-LD)
- Article + FAQPage on every post.
- Product + Offer on money pages (price in GHS, availability, condition = new).
- BreadcrumbList sitewide.

## EEAT and honesty
- Author entity: WebsitesGH Shop (never Stephen's name unless he says so).
- Real specs only. Never claim a spec the supply does not have. Products are new and
  sealed; say so plainly, and never imply a certification or warranty we do not have.
- At least two named external references where a claim needs backing. Never invent a source.

## Conversion (every article earns its keep)
- Ends with a CTA block linking to the exact WooCommerce product or category page for the
  models discussed, plus the tracked WhatsApp-cart path.
- CTAs carry UTM so organic-to-order is measurable (utm_source=organic, utm_medium=article,
  utm_campaign=<cluster>, utm_content=<slug>).
- Lead with model + price in the copy. Price self-qualifies the reader.

## Internal linking (every article)
- Up: to its cluster pillar.
- Sideways: to one relevant comparison or guide.
- Down: to at least one product or category page.
- The deals hub links to every model money page.

## Local signals
- Prices in GHS. Accra / Ghana context. Delivery and pickup mentioned. Real areas named.

## Hard rules
- No em dash (U+2014) anywhere. The publisher refuses any file containing one.
- "Accra International Airport", never "Kotoka".
- No company name attributed to Stephen.
- Never claim done without verifying the live URL returns 200 and the slug matches.

## Per-article file trio
1. `research/<slug>.research.md` - SERP top 10, PAA, AI Overview state, keywords, the angle.
2. `specs/<slug>.spec.md` - the brief built from research (title, H2s, table plan, links, CTA).
3. `articles/<slug>.html` - the final WordPress post body.

## Citation-moat requirements (added 2026-07-15, see GEO-DATA-LAYER.md)
Every money page from here forward MUST also include:
- A Quick Answer capsule in the first 60 words (model, price, key numbers, verdict).
- At least one original data element (grading standard, price context, or benchmark reading).
- Dated pricing ("verified [month year]") and a price table.
- An engineered Q&A block using the exact prompts people type into AI assistants.
- Inline JSON-LD: Product + Offer, FAQPage, and Dataset where original data is present.
- Fact-dense numeric statements over vague adjectives (real benchmarks, Wh, kg, seconds).
