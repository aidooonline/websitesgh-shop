# Tracking templates: the exact strings to paste into each platform

Paste these once per platform. They are what turns "a Google click happened"
into "the exact keyword that earned this cedi".

**Why this is urgent rather than tidy.** A gclid is opaque. It cannot be
resolved back to a keyword, ad group or ad from outside Google's own reports,
and there is no API call that will tell you later. Whatever the ad platform
writes onto the landing URL at click time is the only record you will ever
have. Every click that lands before these are set is permanently anonymous
beyond "Google, somewhere".

The shop captures these into 90-day first-party cookies and stamps them onto
every WhatsApp tap, every add to cart and every order. See
`inc/attribution.php`.

---

## Google Ads

Paste into **Settings > Account settings > Tracking > Final URL suffix**, at
account level so it applies everywhere without touching each ad.

```
utm_source=google&utm_medium=cpc&utm_term={keyword}&utm_content={creative}&wg_mt={matchtype}&wg_cid={campaignid}&wg_ag={adgroupid}&wg_cr={creative}&wg_net={network}&wg_dev={device}&wg_tgt={targetid}
```

Do **not** add `gclid` yourself. Google appends it.

Leave `utm_campaign` out of the account-level suffix and set it per campaign to
a readable name if you want one in the admin screen. The dashboard joins on
`wg_cid`, which is an id and survives a rename, so a missing `utm_campaign`
costs nothing but readability.

### What each one gives you

| Parameter | Lands in | What it is |
|---|---|---|
| `{keyword}` | `utm_term` | **The keyword you bid on.** Blank for Performance Max, DSA and AI Max. |
| `{matchtype}` | `match_type` | `e` exact, `p` phrase, `b` broad, `a` AI Max keywordless |
| `{campaignid}` | `campaign_id` | Numeric campaign id |
| `{adgroupid}` | `adgroup_id` | Numeric ad group id |
| `{creative}` | `creative_id` | The specific ad |
| `{network}` | `network` | `g` Google search, `s` search partners, `d` Display, `ytv` YouTube, `x` PMax |
| `{device}` | `device` | `m` mobile, `t` tablet, `c` computer |
| `{targetid}` | `target_id` | The keyword, audience or DSA target that triggered the ad |

### The one thing to keep straight

**`{keyword}` is the keyword you bid on, not what the customer typed.** The
actual search query only exists in the Search Terms report. This matters: a
broad-match keyword can be triggered by queries that look nothing like it, so
a keyword judged KEEP is a keyword whose *bid* profits, not a query that
profits. When the search terms report is imported in a later sprint the two
sit side by side. Until then, never label `utm_term` "what people searched".

---

## Meta (Click to WhatsApp)

Paste into the ad's **Website URL parameters** field. Meta uses `{{...}}`
macros, and unlike Google it hands over readable names as well as ids.

```
utm_source=meta&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&wg_cid={{campaign.id}}&wg_ag={{adset.id}}&wg_cr={{ad.id}}&wg_pl={{placement}}
```

Meta has no keyword, so `utm_term` stays empty. That is correct, not a fault:
Meta is interest and audience targeting. The decision engine judges Meta on
campaign and creative, never on keyword.

Note the Click to WhatsApp caveat: a CTWA ad that opens WhatsApp directly never
touches the shop, so no cookie is set and no row is logged. Only Meta ads whose
destination is the **website** flow through this attribution. Send paid traffic
to the product page and let the site's own WhatsApp button carry the ref code.

---

## TikTok (Promote)

TikTok Promote gives far less than Ads Manager. Set what you can on the
destination URL:

```
utm_source=tiktok&utm_medium=paid_social&utm_campaign=promote-<name>&utm_content=<video-slug>
```

Use a distinct `utm_campaign` per boosted video, because Promote will not fill
a macro for you. Manual naming is the only join key TikTok leaves you.

---

## Testing it before you spend anything

The whole chain can be proved in five minutes without an ad account.

1. Open the shop in a fresh incognito window with a fake click on the URL:

```
https://shop.websitesgh.com/?gclid=TEST-WGH-1&utm_source=google&utm_medium=cpc&utm_term=blender%20price%20accra&wg_mt=e&wg_cid=22334455&wg_ag=99887766&wg_cr=712345678&wg_net=g&wg_dev=c
```

2. Add something to the cart, then tap **Send order on WhatsApp**.
3. wp-admin > WooCommerce > Attribution. The new row should show the keyword
   `blender price accra` with `exact` under it, and the Product column should
   name the basket rather than reading `#0`.
4. Mark it **Sold**, then on the server:

```bash
cd ~/shop.websitesgh.com/wp-content/themes/websitesgh-shop/dashboard/api
php artisan wgh:sync
```

The dashboard row now carries the keyword, the match type, the campaign and ad
group ids, and the basket. That is everything sprint 2's join needs.

---

## Where each field ends up

Shop table `wp_wghs_attribution`, mirrored into the dashboard's
`attribution_events` and, for orders, onto `orders`:

```
utm_source      utm_medium     utm_campaign   utm_term       utm_content
utm_id          match_type     campaign_id    adgroup_id     creative_id
target_id       network        device         ad_placement   cart_items
```

`cart_items` holds the basket behind a cart tap as
`productId:qty:lineTotal,productId:qty:lineTotal`. The shop is cart first, so
this is what lets revenue be attributed to a product at all on the main order
path. Before it existed, every cart tap logged product 0 at value 0.
