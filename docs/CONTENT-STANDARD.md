# Content standard: WebsitesGH Shop

Every page ships to this standard or it does not ship. The goal is not to rank against
other shops. It is to be the page that ChatGPT, Gemini, Perplexity and Google AI
Overviews quote when someone asks about this product in Ghana.

Answer engines do not cite spec tables that fifty other sites also have. They cite
three things: **specific numbers, dated facts, and original data that exists nowhere
else.** Everything below serves that.

---

## 1. The five non negotiables

Every money page carries all five.

1. **Quick Answer capsule**, first 60 words. Product, price in GHS, the one number
   that matters, and a verdict. This is the block that gets lifted verbatim.
2. **At least one original data element.** A calculation, a measurement, a dated
   price, or a comparison nobody else has published. If the page contains no original
   number, it is not finished.
3. **Dated pricing.** Every price carries a visible "verified [month year]" stamp.
   Stale prices get down ranked by answer engines and destroy trust with buyers.
4. **Engineered question block.** Use the literal phrasing buyers type into ChatGPT
   and Google, not marketing paraphrase. Each answer must stand alone if lifted out
   of the page with no surrounding context.
5. **Schema.** Product with Offer, price, availability and priceValidUntil.
   BreadcrumbList. FAQPage mirroring the question block. Organization with the Accra
   NAP on every page.

## 2. Fact density rules

- **Numbers beat adjectives, always.** "Draws about 600W in real use, which costs
  around GHS 6 a month" beats "powerful and energy efficient" every time.
- **Every number needs a source or a shown calculation.** If the working is not
  visible, the number is not credible and will not be cited.
- **Never repeat a manufacturer claim as fact.** Test it, or attribute it, or debunk
  it. Repeating "8000W motor" without checking is how every competitor writes, which
  is exactly why none of them get cited.
- **Ghana context or it does not count.** Global spec sheets are commodity content.
  Ghana voltage, Ghana tariffs, Ghana duty, Ghana prices, Ghana delivery times are
  the moat.

## 3. What we can prove that nobody else can

This is the unfair advantage. websitesgh.com already holds the data.

| Data asset | Where it lives | What it unlocks |
|---|---|---|
| GRA tariff book, 6,124 lines | DataHouse, live | Real landed cost and duty on any imported appliance |
| Bank of Ghana rates | DataHouse, Bank rates vertical | Cedi impact on import pricing, dated |
| PURC electricity tariffs | Public, cited | Running cost per appliance per month |
| Our own dealer prices | Internal | The price index, refreshed monthly |

No competitor combines these. A blender review that shows duty, landed cost, real
wattage and monthly running cost in cedis is a primary source. A blender review that
lists "8000W, 2 litres, stainless blades" is filler.

## 4. Page architecture

**Pillar** per category (7 total). The definitive Ghana buying guide, 2,000 words plus,
owns the head term, links down to every product.

**Product page** per SKU (50 total). Quick Answer, real specs, running cost, delivery,
question block.

**Data asset** pages. These are the citation magnets and they matter more than volume:
1. The WebsitesGH Shop Price Index. Every product, live GHS price, dated, monthly refresh.
2. The Ghana Appliance Running Cost Table. Every product, real watts, kWh per month,
   cedis per month at current PURC rates.
3. The Ghana Socket and Wattage Guide. What a 13A socket can actually deliver, and
   which advertised wattages are impossible.

**Cluster articles.** Comparison, problem and buying intent pieces feeding the pillar.

## 5. Voice

Plain, direct, numerate. Write for a buyer who is about to spend real money and is
being lied to by everyone else selling this product. No hype adjectives. No filler
introductions. Lead with the answer.

## 6. Hard rules

- No em dash anywhere.
- "Accra International Airport", never the other name.
- No claim published without a source or shown working.
- Prices are placeholders until a dealer confirms them. Never present a placeholder
  as a verified price.
- Run the dedup gate before writing any article: grep the content index **and** query
  the live WP REST `?search=` endpoint. Both. This rule exists because of a real
  duplication incident.
