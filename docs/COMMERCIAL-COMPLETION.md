# Commercial completion plan

The gap the owner is right about: the shop has product pages and a basic blog
list, but not the full furniture a commercial ecommerce site and a real blog
need. This plan lists every missing piece and tracks execution. Nothing is
marked done until it is built and pushed.

## A. Blog, matching websitesgh.com r26 structure  [DONE]
Confirmed live structure on websitesgh.com:
- Two column article: wide body + right rail (r26-art-body + r26-art-side)
- Table of contents, auto built from H2/H3
- Reading progress bar
- Share bar (WhatsApp, Facebook, X, copy link)
- Author box
- Related posts
- Breadcrumbs
- Sticky right rail carrying the ad slot (this is the ad plugin home)

Tasks:
1. single.php rebuilt to this two-column structure with TOC + progress + share
2. Blog listing (index.php) already a lead + river; keep, align styling
3. Category and tag archives use the same shell
4. Author box, share bar, TOC, progress as template parts / JS

## B. Commercial pages every store needs  [DONE: FAQ, Track, Warranty, Coverage, Wholesale added]
Present now: About, Contact (form), How to Order, Delivery & Payment,
Returns, Privacy, Terms, Price Index, Running Costs, Guides.
Missing for a full commercial site:
1. FAQ page (consolidated, schema'd)
2. Track my order / Order status
3. Warranty page (distinct from returns)
4. Shipping/coverage areas page with the zones
5. Wholesale / bulk enquiry page (dealers, offices, resellers)
6. Reviews / testimonials surface
7. Search results page styling
8. 404 already themed; confirm
9. My account pages themed (login, orders)
10. Cart and checkout confirmed classic + themed

## C. Navigation  [DONE: primary + 3 footer menus, dead links fixed]
1. Primary menu: Home, Shop (with category dropdown), Guides, Price Index,
   About, Contact
2. Footer menu: all policy + info pages, grouped
3. Mobile menu parity

## D. Homepage completeness  [AUDIT]
Hero, proof, categories, featured, how-it-works, guides, CTA. Confirm all
render and are wired.

## Execution order
A (blog) first since it was explicitly asked for, then C (nav), then B
(pages), then D (home audit). Push after each block, verify each push.
