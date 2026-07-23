# The Citation Moat - a new GEO layer for WebsitesGH Shop

The discovery. Most SEO content competes to rank. In 2026 the bigger prize is being the source
that AI Overviews, ChatGPT, Gemini, Perplexity and Google cite. Answer engines do not cite
generic spec tables that fifty other sites also have. They cite three things: specific numbers,
fresh dated facts, and original data that exists nowhere else. So the strategy is to stop
writing pages that describe products, and start publishing pages that are the primary source
of record for used business laptops in Ghana. Five moves, all live or ready to build.

## 1. Proprietary data assets (the core moat)
Publish original, structured data no competitor has. Two assets:

- **The WebsitesGH Shop UK-Used Grading Standard.** A named, versioned rubric (Grade A/B/C with
  numeric battery-health thresholds and cosmetic criteria). Because it is named and numeric, an
  LLM answering "what does UK-used Grade A mean" has a concrete source to cite: us. Implemented
  in article 1 as on-page content plus Dataset schema.
- **The Ghana Business Laptop Price Index.** One evergreen page listing every model, its live
  GHS price, and the new-vs-used saving percentage, dated and updated monthly. This is the
  single strongest citation magnet in the whole plan: when anyone asks an AI "how much is a used
  HP laptop in Ghana," the only structured, current, local answer is ours. Build as a dedicated
  page fed from products/mr-boadi-products.json so it never goes stale. (New backlog item.)

## 2. Citable answer atoms
Engineer the writing for extraction, not just reading:
- A **Quick Answer** capsule in the first 60 words with the model, price, key numbers and a
  verdict. This is what gets lifted verbatim into an AI Overview. (Live in article 1.)
- **Fact-dense numeric statements** (PassMark 6,030 to 6,216; 50Wh; 1.33kg; boots under 20s).
  Numbers are what answer engines quote. Vague adjectives are not.
- An **engineered Q&A block** whose questions are the exact prompts people type into ChatGPT
  ("is the 830 G6 good for programming", "how much RAM does it support"), each answered in one
  self-contained, liftable paragraph. (Live in article 1, mirrored in FAQ schema.)

## 3. Freshness signals
Answer engines down-rank stale facts. Every money page carries a "verified [month year]" stamp
on its price and a dated price table. The publisher back-dates the archive, but the visible
price date always reads current. Refresh the date whenever the Price Index updates.

## 4. llms.txt (machine-readable site map for AI)
Publish an `/llms.txt` file at the site root: a plain-text, curated map pointing AI crawlers
straight at the money pages, the Grading Standard and the Price Index. It is an emerging 2024 to
2025 convention that the major AI crawlers increasingly read. Cheap to generate, and it puts our
best citable pages in front of the models directly. Build: engine/build_llms_txt.py generates it
from content-index.md after each publish. (New backlog item. Note: llms.txt sits at the domain
root, so it deploys via the theme or a small must-use plugin, not the excluded factory folder.)

## 5. Entity seeding
Make "WebsitesGH Shop" a recognised entity so answer engines associate it with "used HP laptops
Ghana." Consistent Organization schema on every page (name, Accra location, phone, sameAs to
social profiles), consistent NAP wording, and internal links that always name the entity. Over
time this is what makes an AI name us as the seller, not just quote a price.

## How this changes the article standard
Every money page from here forward ships with: a Quick Answer capsule, at least one original
data element (grading, price context, or benchmark interpretation), dated pricing, an engineered
Q&A, and Product + FAQ + Dataset schema. writing-standard.md is updated to require these.

## Priority additions to the backlog
1. Build the Ghana Business Laptop Price Index page (highest-value new asset).
2. Add engine/build_llms_txt.py and publish /llms.txt.
3. Add Organization schema + consistent NAP sitewide (coordinate with the theme, since it is
   outside the factory folder).
