# WGH Intelligence: engineering specification

One north star governs every line of this system: sell more, at profit. Every
table, endpoint, view and AI prompt is judged by one test, does it help decide
what to sell, where to spend, and what to cut. Anything that does not serve
that test does not get built. No vanity metrics, no dashboards that look busy
and say nothing. Signal to action, every screen.

This document is the contract. Each sprint below has: purpose tied to selling,
scope, the exact data or endpoints, the failure modes to design against, and
the acceptance test that must pass before the sprint is called done. "Done"
means the acceptance test passes on real data, not that code exists.

--------------------------------------------------------------------------
## System overview

A standalone Laravel (API) + React (UI) + Postgres application that consolidates
three data streams, joins them into one picture per product/keyword/channel/
creative, judges each with a rule engine, remembers every decision, and layers
a Claude agent that reads the whole picture and tells the owner the single
highest-value move to make more sales.

Streams:
1. WooCommerce (automatic): orders, order items, the wghs_attribution table.
2. Ad platforms (manual CSV): Google Ads, Meta, TikTok exports.
3. Owner input: delivered/failed, dealer cost, MoMo received.

The join is the point of the whole system: spend -> tap -> order -> profit,
traceable end to end, because the ref-code loop already ties WhatsApp chats to
attribution rows. No off-the-shelf tool can do this join; that is the moat.

Stack rationale: Laravel + Postgres matches the house-price portal and Datari,
so it is familiar and maintainable. React for a dashboard lived in daily.
Hosted on the shop server. Code in the websitesgh-shop repo under /dashboard.

--------------------------------------------------------------------------
## Canonical data model (Postgres)

Designed once, here, so no sprint invents its own shape. All money stored in
minor units where possible and currency-tagged, because ad spend is USD and
sales are GHS and mixing them is the classic reporting bug.

orders
  id, woo_order_id (unique), created_at, status,
  revenue_ghs, currency='GHS',
  customer_ref (the WG-XXXX ref code, nullable),
  click_id, click_type, utm_source, utm_medium, utm_campaign,
  placement, delivered (bool null=unknown), delivery_failed (bool),
  dealer_cost_ghs (null until entered), delivery_cost_ghs (null),
  momo_received (bool), profit_ghs (computed, null until costs entered),
  customer_phone (nullable, raw), customer_phone_sha256 (nullable, hashed
    for Enhanced Conversions upload; raw never leaves the server),
  synced_at

order_items
  id, order_id -> orders, woo_product_id, product_name, qty, unit_price_ghs

attribution_events (mirror of wghs_attribution, pulled nightly)
  id, woo_attr_id (unique), created_at, click_id, click_type, product_id,
  product_name, price_ghs, placement, utm_source, utm_medium, utm_campaign,
  status, ref, conv_value_ghs, order_id

ad_spend (from CSV imports, normalized across platforms)
  id, platform (google|meta|tiktok), period_start, period_end,
  campaign, ad_group, keyword (null for non-search), match_type,
  impressions, clicks, spend_usd, currency='USD',
  source_file, imported_at
  UNIQUE(platform, campaign, ad_group, keyword, period_start, period_end)
  -- the unique key prevents double-counting a re-imported file

keywords (the living registry the decision engine judges)
  id, keyword, match_type, ad_group, campaign, landing_url,
  first_seen, last_seen,
  lifetime_spend_usd, lifetime_clicks, lifetime_taps, lifetime_orders,
  lifetime_revenue_ghs,
  current_verdict (keep|watch|fix|kill), verdict_reason, verdict_at,
  owner_decision (keep|hold|kill|null), owner_decision_at

decisions (the memory: every verdict and every owner action, immutable log)
  id, dimension (keyword|product|channel|creative), entity_ref,
  verdict, reason, suggested_action,
  evidence_json (the numbers behind it at decision time),
  source (engine|owner|agent), created_at

manual_entries (audit of owner-entered facts)
  id, order_id, field, old_value, new_value, entered_at

agent_briefings
  id, trigger (import|manual), created_at, model_used,
  period_covered, summary_md, top_action, evidence_json, tokens_cost

Every table that holds money carries an explicit currency. Every join that
crosses currency converts through a stored fx_rate row (date, ghs_per_usd) so
historical numbers stay correct when the rate moves. This one discipline
prevents the single most common reporting failure.

--------------------------------------------------------------------------
## SPRINT 1: foundation + WooCommerce connector

Selling purpose: nothing can be judged until real orders are in the system.
This sprint proves the truth source flows in, cleanly and repeatably.

Scope:
- Laravel app scaffold, Postgres, migrations for the full model above (all
  tables created now, so later sprints never migrate under pressure).
- WooCommerce connector: pull orders, order_items, and wghs_attribution.
  Two options, build the safer one: a read-only DB connection is fragile
  across hosts, so instead the shop exposes a signed REST endpoint
  (wghs/v1/export) that returns new orders + attribution since a cursor, and
  the dashboard pulls nightly + on demand. Authenticated with a shared secret.
- Idempotent sync: woo_order_id and woo_attr_id are unique; re-running the
  sync never duplicates. Cursor stored so each run only pulls the delta.
- FX rate table seeded, with a command to add today's rate.

Failure modes to design against:
- Double import (re-run creates duplicate orders): solved by unique keys +
  upsert, tested by running sync twice and asserting identical row counts.
- Partial pull (network drop mid-sync): each record committed in a
  transaction; cursor advances only on full success, so a failed run re-pulls
  cleanly next time.
- Currency mixing: orders GHS, tagged at insert.
- Time zones: shop stores UTC; dashboard stores UTC; display converts to
  Africa/Accra. Never store local time.

Acceptance test (must pass on real data):
- Trigger sync against the live shop. Assert: order count in dashboard equals
  order count in WooCommerce for the period; every order has its items; every
  attribution row is mirrored; running the sync a second time changes zero
  rows. Show a real order end to end in the DB with its ref code and click id.

--------------------------------------------------------------------------
## SPRINT 2: CSV ingestion + the join engine

Selling purpose: spend is half the profit equation. This sprint makes "what
did this keyword cost, and what did it earn" answerable.

Scope:
- Upload screen (API endpoint + minimal UI): accept Google Ads, Meta, TikTok
  CSV exports.
- Three parsers, one per platform, each mapping that platform's real column
  names to the ad_spend shape. Column maps documented, because Google renames
  columns between report types; the parser detects the header row and maps by
  name, never by position.
- Normalization: currency to USD (already USD from these platforms), dates to
  UTC period ranges, keyword text lowercased and trimmed to match the keywords
  registry.
- The JOIN engine: match ad_spend rows to attribution_events and orders.
  - Search keywords join to attribution by utm_campaign + the keyword text and
    by click_id where present (click_id is the strongest join, exact).
  - Non-search (Meta/TikTok) join by utm_campaign + utm_source.
  - Output: per keyword, the chain spend_usd -> clicks -> taps -> orders ->
    revenue_ghs -> profit_ghs (once costs entered).
- Update the keywords registry lifetime_* totals on each import.

Failure modes:
- Re-importing the same file double-counts spend: the ad_spend UNIQUE key +
  upsert prevents it; tested by importing the same file twice, asserting spend
  unchanged.
- Keyword text mismatch (Google "Blender Price" vs our "blender price"):
  normalized lowercase/trim on both sides before join.
- Currency confusion in the join: spend USD, revenue GHS, profit computed via
  the dated fx_rate; the join stores both and the computed profit, never a
  silently mixed number.
- Orphan spend (a campaign in the CSV with no matching attribution): surfaced
  as "unmatched spend" rather than hidden, because unmatched spend is itself a
  finding (a leak or a tracking gap).

Acceptance test:
- Import a real Google Ads CSV. Assert: total spend in dashboard equals total
  in the CSV to the cent; at least one keyword shows the full chain spend ->
  clicks -> taps -> orders; re-importing changes nothing; unmatched spend is
  reported, not dropped.

--------------------------------------------------------------------------
## SPRINT 3: the rule-based decision engine

Selling purpose: this is where data becomes "do this." The engine turns every
keyword, product, channel and creative into a verdict with a reason.

Scope:
- The verdict rules, run on demand after each import, for all four dimensions:
  KEEP:  cost_per_order <= profit_per_order (profitable)
  WATCH: spending but < 100 clicks OR < 14 days of data (too early), with a
         countdown to when it can be judged
  FIX:   healthy clicks and taps but no sale -> the leak is page or price, not
         the keyword (this distinction is the money insight; it stops the owner
         killing a good keyword when the real problem is the landing page)
  KILL:  >= 14 days AND >= one profit-per-order in spend (~$8.75) AND zero
         orders (both conditions, so thin-data keywords are never killed)
- Every verdict writes a decisions row with evidence_json (the exact numbers at
  that moment) so the memory is complete and auditable.
- The suggested_action per verdict, goal-locked to selling:
  KEEP -> "scale: raise budget/bid on this, it profits"
  FIX  -> "the page or price is losing the tap; check the landing page"
  KILL -> "cut it; it has spent $X over Y days with no sale"
- Pattern detection (the compounding brain): after stamping, the engine scans
  the kill/keep sets for shared traits (match type, presence of price/accra,
  category-level vs specific-product) and records observed patterns, e.g.
  "4 of 5 killed keywords were bare category terms; specific-product terms
  convert better."

Failure modes:
- Killing on thin data: prevented by the dual condition (time AND spend).
- Rules judging across currencies: cost_per_order in USD, profit_per_order
  converted from GHS via dated fx; compared in the same currency, always.
- A verdict with no evidence: enforced; the engine refuses to write a verdict
  without an evidence_json payload.

Acceptance test:
- Run the engine on the joined dataset. Assert: every keyword has a verdict and
  a reason; a keyword with 5 days of data is WATCH with a countdown, never
  KILL; a keyword with good taps and no sale is FIX, not KILL; every verdict
  has evidence; at least one pattern is detected and stored.

--------------------------------------------------------------------------
## SPRINT 4: the React dashboard (macro -> meso -> micro)

Selling purpose: the owner must see, in seconds, where the money is made and
lost, and drill from the whole business down to one keyword.

Scope:
- Macro view: this period. Revenue (GHS), ad spend (USD, converted), profit,
  cost per delivered order, by channel. The four numbers that decide the month,
  large and plain. Trend vs last period.
- Meso view: per channel / campaign / product. Sortable by profit, by cost per
  order, by verdict. This is the "what is winning, what is leaking" screen.
- Micro view: per keyword. The full chain, the verdict, the reason, the
  suggested action, and the decision history. This is the verdict board.
- Every number is a link down a level; every view answers one selling question,
  stated at the top of the screen so no screen is decoration.
- Performance: server-side aggregation (Postgres does the math, React renders);
  no shipping raw rows to the browser. Views paginate and cache per import.

Failure modes:
- Slow dashboards that ship everything to the browser: prevented by
  server-side aggregation and cached period snapshots.
- Currency shown wrong: every money value labeled with its currency in the UI,
  USD spend and GHS revenue never share an unlabeled column.
- A screen that shows numbers but no action: banned by design; each view
  carries its question and, at micro, its verdict + next action.

Acceptance test:
- Open the dashboard on real data. Assert: macro shows the four numbers with
  correct currency; drilling channel -> campaign -> keyword works and the
  numbers reconcile at each level (child sums equal parent); the micro view
  shows verdict + reason + action per keyword; every view loads under ~1s on
  the real dataset.

--------------------------------------------------------------------------
## SPRINT 5: owner input, profit truth, and the change-file export

Selling purpose: profit is only real once dealer cost and delivery are known,
and decisions are only useful once they leave the dashboard and reach Google.

Scope:
- Fast inline entry: per order, mark delivered/failed, enter dealer_cost_ghs
  and delivery_cost_ghs, mark momo_received. Keyboard-fast, because this is
  done often. Each entry writes a manual_entries audit row and recomputes
  profit_ghs immediately.
- Profit truth: profit_ghs = revenue_ghs - dealer_cost_ghs - delivery_cost_ghs;
  the profit bands (green < $6 equiv, amber, red > profit-per-order) recompute
  from real margins, so the whole decision engine sharpens as real costs land.
- Enhanced Conversions export: the offline conversion file includes, per
  converted order, the gclid AND the customer's phone hashed with SHA-256
  (normalized to E.164 first, e.g. +233..., then lowercased-trimmed then
  hashed, per Google's spec). Research shows gclid + first-party data gives a
  median 10% lift in recorded conversions over gclid-only, and Ghana is
  phone-first so the match rate is high. The RAW phone never leaves the server;
  only the hash is written to the export. Two conversion files are produced:
  the standard offline-conversion CSV (gclid, name, time, value, currency) and
  the Enhanced Conversions for Leads CSV (adds the hashed phone column), so the
  owner can use whichever Google path is active. Data Manager (June 2026+) via
  its SFTP connector accepts the file with no fixed template; the manual CSV
  path still works.
- Weekly export reminder: the dashboard tracks the last export date and shows a
  prominent nudge when 7 days have passed (research: Smart Bidding wants daily-
  to-weekly consistent uploads, and needs ~30 conversions/month to stabilise,
  so consistency matters more than volume early). The reminder states how many
  unexported converted orders are waiting.
- Version B action export: the owner clicks keep/hold/kill on the verdict
  board; the dashboard writes a Google Ads Editor change-file CSV (pause these
  keywords, keep these, add these negatives) in the exact Editor import format
  we already use. Every click also writes a decisions row (source=owner), so
  the memory records not just the engine's verdict but the owner's action.
- Failed-delivery accounting: a failed delivery is a real ad cost with no
  revenue; the engine counts it against the keyword that drove it, so cost per
  DELIVERED order is honest.

Failure modes:
- Profit computed on partial costs (dealer entered, delivery not): profit stays
  null and shows "awaiting costs" rather than a wrong number.
- Change file in the wrong format: validated against the Editor import spec and
  round-tripped (export, re-import to a test) before the sprint is done.
- Owner decision lost: every click persisted immediately, optimistic UI with
  server confirm.

Acceptance test:
- Enter real costs on a real order; assert profit recomputes correctly and the
  order's keyword verdict updates. Click kill on 3 keywords, keep on 5; assert
  a valid Editor CSV is produced that imports cleanly into Google Ads Editor;
  assert every click wrote a decisions row.

--------------------------------------------------------------------------
## SPRINT 6: the Claude selling agent

Selling purpose: the capstone. A strategist that reads the whole consolidated
picture and tells the owner the single highest-value move to sell more, with
the risk named. Advises, never acts.

Scope:
- Backend service (Laravel) that, on each data import, assembles the
  consolidated dataset into a compact, structured payload: the period numbers,
  per-channel and per-keyword performance, verdicts, patterns, unmatched spend,
  profit truth. Compact because tokens cost money and signal beats bulk.
- Two-model split, matching the owner's other projects:
  - Claude (Anthropic API) for the reasoning: the weekly briefing, the
    strategic second opinions, the ask-anything answers.
  - fal.ai any-llm for the cheap bulk: first-pass tagging/summarizing of large
    keyword sets before the Claude call, so Claude reasons over a distilled
    input, not raw thousands of rows.
- The system prompt is goal-locked and balanced: the agent's job is to increase
  sales at profit; it weighs growth against risk and always names the trade-off;
  it is concise and action-first; it ends every output with the one move to
  make now. No filler, no hedging essays, no restating the data back. The prompt
  forbids vague advice: every recommendation must cite the number that drives it
  and the expected effect on sales.
- Three outputs:
  1. Briefing (fires on import): biggest win, biggest leak, the one highest-
     value action, each with its number and expected sales impact.
  1b. The agent explicitly watches the offline-conversion feedback loop, since
      that is the single strongest growth lever: it flags when the export is
      overdue, when the monthly conversion count is under ~30 (below which
      Smart Bidding under-performs), and when match rate looks low, because
      those directly throttle how fast Google learns to find buyers. Feeding
      confirmed sales back to Google is how the SAME budget produces more sales
      over time; the agent treats protecting that loop as a first-order goal.
  2. Second opinion (on any flagged keyword/product): the why behind the
     verdict and a sharper suggestion than the rule alone.
  3. Ask-anything: owner types a question, agent answers from the real data
     only, never generic.
- Every briefing stored (agent_briefings) with its evidence and token cost, so
  advice is auditable and the owner can see the agent's track record.
- Guardrails: the agent recommends, the owner acts. It never triggers the
  change-file export itself. It cannot spend or pause anything. Stated in the
  prompt and enforced in code (no write access to the action layer).

Failure modes:
- Vague or verbose advice (the exact failure the owner called out): the prompt
  mandates action-first, number-cited, one-move-per-output; outputs that do not
  name a number and an action are regenerated. Balanced but never wishy-washy.
- Hallucinated numbers: the agent is given the data and instructed to cite only
  provided figures; the payload is the single source, and the UI shows the
  evidence beside the advice so any drift is visible.
- Token blowout: fal.ai distills first, the Claude payload is compact and
  capped, cost logged per briefing.
- Advice detached from the goal: the system prompt has one objective, sell more
  at profit, and every output is scored against it.

Acceptance test:
- Import real data; assert a briefing is generated that names the biggest win,
  the biggest leak, and one specific action, each citing a real number from the
  dataset and an expected effect on sales; ask a real question and get an answer
  grounded in the data; assert the briefing is stored with its cost; assert the
  agent has no code path to the action/export layer.

--------------------------------------------------------------------------
## Cross-cutting engineering standards (all sprints)

- Every money value carries a currency; cross-currency math goes through a
  dated fx_rate. No unlabeled money, ever.
- Every import is idempotent; re-running never double-counts.
- Every verdict and owner action is an immutable decisions row with evidence.
- No screen without a question it answers and, where relevant, an action.
- Server-side aggregation; the browser renders, it does not compute.
- Commits authored as Stephen Aidoo <aidooonline@gmail.com>.
- No em dash anywhere. PAT scrubbed after every push. No feature called done
  until its acceptance test passes on real data, verified, not assumed.
- The offline-conversion feedback loop is treated as the primary growth
  engine: every confirmed sale must be exportable back to Google with its gclid
  and hashed phone, promptly and consistently, because that is what teaches
  Smart Bidding to buy more buyers and fewer tappers with the same budget.
- The whole system answers one question at every level: what do I do next to
  sell more at profit. If a feature does not help answer that, it is cut.
