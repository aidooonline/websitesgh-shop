# Conversion plan: making the site a machine that sells

The content is the differentiator. This document is how the site turns that content
into orders. Build order follows Sprint 2 and Sprint 3.

---

## 1. Positioning

**"The shop that checks the numbers."**

This is not a slogan bolted on afterwards. It falls out of the research. Two findings
so far, and both are the same shape:

- Every blender in Ghana is sold as 4500W or 8000W. On a 13A socket the ceiling is
  2,990W. The claim is impossible.
- Every power bank is sold on a mAh figure the device never receives. A 30,000mAh
  bank delivers about 15,500mAh.

Every competitor repeats the box. We do the arithmetic and show the working. That is
the whole brand, and it is defensible because it takes work nobody else will do.

The trust promise, stated plainly on every page: **you pay when it reaches you.**

---

## 2. Homepage structure

Order matters. Each block earns the scroll to the next.

1. **Hero.** One line of positioning, one line on pay on delivery, primary CTA
   "Shop now", secondary "Order on WhatsApp". No carousel. Carousels kill conversion
   and they kill mobile performance.
2. **Trust strip**, immediately under the hero. Four items, icon and short label:
   pay on delivery, same day in Accra, check before you pay, real prices verified
   this month. This is the single highest leverage block on the page.
3. **Featured products**, 8 items. Real photo, name, GHS price, stock state, one
   click add. Price must be visible without a tap.
4. **The proof block.** A short extract from a data asset with a link to the full
   page. For example the running cost table showing a blender costs about GHS 6 a
   month. This is what makes us different from every other Ghana store and it belongs
   above the fold on second scroll, not buried in a blog.
5. **Category tiles**, 7 categories.
6. **How ordering works**, three steps: choose, we call to confirm, pay the rider.
   Removes the main objection to buying online in Ghana.
7. **Latest guides**, 3 posts, pulling from the content engine.
8. **Final CTA band** in green with the WhatsApp option.

## 3. Call to action rules

- **One primary CTA per screen.** Green `#0E8C5A` filled button, white text.
- **Secondary is WhatsApp**, outline style, never competing visually with the primary.
- CTA copy states the outcome, never the mechanism. "Order and pay on delivery",
  not "Submit" or "Click here".
- **Sticky mobile bar on product pages**: price on the left, "Order now" on the right.
  Always visible. Mobile is the whole market here.
- Gold `#E2A013` is reserved for price, savings and urgency. Never for the primary
  button. If gold is everywhere it signals nothing.

## 4. Reusable patterns

Build these once as template parts, use everywhere.

| Pattern | Where | Job |
|---|---|---|
| Quick Answer capsule | Top of every product and guide page | Gets lifted into AI Overviews, and answers the buyer in 5 seconds |
| Price block with verified stamp | Every product | Trust plus freshness signal for answer engines |
| Running cost callout | Every appliance product | Original data, nobody else has it |
| Claim check callout | Products with inflated specs | The brand in one box |
| Trust strip | Home, product, checkout | Kills the pay first objection |
| Question block | Every money page | Mirrors into FAQ schema |
| Sticky order bar | Product, mobile | Conversion |
| WhatsApp handoff card | Everywhere | Second path for buyers who will not use a form |

## 5. Banner style

Flat, light, typographic. No stock photography, no gradients, no glow. The parent
theme's Aurora glow effects belong to a dark theme and were removed for a reason.

- Background `#E9F7F0` green pale or `#FBEFD2` gold pale
- Rule above and below in `#EDEAE0`
- Headline in Figtree semibold, ink `#14201A`
- One number set large in green or gold. The number is the hero, not an image.

Example: a banner whose entire content is **"GHS 6.11"** large, with "what a 2L
blender actually costs to run per month in Ghana" underneath. That is a banner no
competitor can copy without doing the research.

## 6. Pages to write

| Page | Job |
|---|---|
| Home | Convert and route |
| About | Why we check the numbers. Founder led, plain, no corporate voice. Names the method, not a company. |
| How to order | Kill the pay first objection, step by step |
| Delivery and payment | Zones, timing, what pay on delivery means exactly |
| Returns | Short, human, specific. Vague returns policies lose sales. |
| Price index | The citation magnet. Every product, live price, dated. |
| Running cost table | The second citation magnet |
| Contact | Phone, WhatsApp, hours, Accra location |

## 7. Measurement

Nothing counts as working until it is measured.

- GA4 ecommerce: view_item, add_to_cart, begin_checkout, purchase
- Google Ads conversion on `order-received` with value and currency
- WhatsApp click as a separate conversion, since it is a real order path here
- Reconcile reported revenue against WooCommerce weekly. If they disagree, the
  tracking is wrong, not the store.

## 8. What is deliberately not being built

- No carousel or slider
- No popup on entry
- No countdown timers or fake scarcity. The brand is "we check the numbers".
  Fake urgency destroys that in one visit.
- No payment gateway at launch. Pay on delivery is the model.
