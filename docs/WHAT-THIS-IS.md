# What this system is

Written for someone who has never seen the code. Every product name, currency
and category below is a placeholder: this describes a **pattern**, and the
pattern is not specific to kitchen appliances, to Ghana, or to WhatsApp.

If you are reading this to write ad copy, section 6 is the pitch and section 7
is the honest list of who it is not for.

---

## 1. The problem it solves

Some shops lose the sale before they can measure it.

A visitor arrives from an ad, browses, and then the transaction leaves the
website. They message on WhatsApp. They ring the number. They walk into the
shop with the product name on their phone. They fill in an enquiry form and get
called back.

The moment that happens, the ad platform goes blind. Google, Meta and TikTok can
see the click. They cannot see the sale. So the optimisation they do on your
behalf is optimising for **clicks that look promising**, not for money.

The owner goes blind at the same moment, and in a worse way. Sales are happening;
they just cannot be traced back to what caused them. So the honest answer to
"which ad is working" is a shrug, and the next month's budget is set by feel.

**This is the normal condition for a very large number of businesses:** every
service business, every high-consideration purchase, every market where cash on
delivery beats card, every shop whose customers prefer to talk to a person.
Standard analytics assumes the sale happens on the website. When it does not,
the tooling does not degrade gracefully. It just reports zero.

---

## 2. The one number the whole thing turns on

Every decision in this system reduces to a single comparison.

**What is one order worth to you, after everything you pay to fulfil it?**
**What does it cost you in advertising to get one?**

If the first is bigger, spend more. If the second is bigger, you are paying to
lose money and selling harder makes it worse.

That first number is **profit per order**, and almost nobody has it to hand. Most
owners know their turnover and their ad spend, and treat the gap between them as
profit. It is not. The supplier, the delivery, the packaging and the returns all
come out first, and what is left is usually a fraction of what the top line
suggested.

Two consequences follow, and they are the reason this system exists:

- **The same ad is a good ad or a bad ad depending on a number most people are
  guessing at.** Not a small difference. A keyword that looks like a disaster at
  an assumed margin can be the best thing in the account at the real one.
- **Profit per order has no ceiling, while cost per order has a floor.** You can
  only squeeze acquisition cost so far. Raising order value, margin, or repeat
  rate lifts every ad in the account at once, on the same day.

Most analytics tools work hard on the cost side and ignore the value side
entirely. This one is built the other way round.

---

## 3. What it does, in three parts

### The watcher
Sits on the shop. Every time a visitor takes the action that ends the digital
trail (opens the chat, taps the number, submits the enquiry) it records what
brought them: the product, the ad, the campaign, the search term, the device.

It is the receipt for a transaction that has not happened yet, kept for the day
it does.

It also tells a customer from a crawler. That sounds like a detail. It is not: on
one real shop, a funnel that looked catastrophically broken was mostly search
engine robots following add-to-cart links, and acting on that reading would have
meant a month rebuilding a checkout that was working fine.

### The book-keeper
Pulls those records together with the orders that actually completed and what
was actually spent on advertising, across every platform. Converts currencies at
a dated rate, so a month closed in March keeps March's rate when it is read again
in September.

Then it answers the questions nothing else could: what did this ad really return,
what does this product really earn, does this customer ever come back, and what
do people buy together.

### The judge
Reads all of it and says one of four things about every ad, keyword and product:

- **KEEP.** It pays for itself. Put more money here.
- **WATCH.** Spending, but too early to judge. It says exactly when it can be
  judged.
- **FIX.** Interested people arrived and did not buy. The problem is after the
  click, so this is a page or price problem, not an ad problem.
- **KILL.** Old enough, spent enough, sold nothing.

Every verdict is stored with the numbers behind it, so it can be argued with six
months later.

**FIX is the distinction that makes this worth building.** Without it, an ad
bringing plenty of genuinely interested people who then bounce off a bad price
looks identical to one bringing the wrong people entirely. The first is worth
fixing and the second is worth switching off, and treating them the same means
turning off demand you already paid to create.

---

## 4. The rules it will not break

These are the design decisions that determine whether the output can be trusted.
They are unglamorous and they are the whole product.

**A blank is never a zero.** If a cost is unknown, it stays unknown and the
product is excluded from the margin. Filling the gap with zero would make it look
like pure profit and bend every verdict in the flattering direction. Half a
margin reported as a whole margin is worse than no margin, because it looks like
knowledge.

**Coverage travels with every number.** A margin measured on three of forty
products is reported as exactly that, everywhere it appears.

**Every verdict records whether it was decided against a measured figure or a
guessed one.** A KILL decided on a guess and a KILL decided on a measurement are
not the same claim, and nobody remembers which was which later.

**Nothing is deleted, only labelled.** Cancelled orders stay. Robot traffic stays.
The reader decides what to count. A classifier that turns out to be wrong must not
have destroyed the evidence of its own mistake.

**One event belongs to one source.** When three campaigns could each claim the
same sale, none of them gets it. Sharing a number out on a guess produces
attributed revenue larger than the money actually taken, and a number split on a
guess looks like knowledge.

**It advises, it never acts.** No budget moves, no ad pauses, no campaign changes
by itself. Decisions leave as a file a human applies. An automated system that
can spend money is a different risk category, and this is not it.

**Every report gets rendered and looked at before shipping.** Several serious
errors in development were invisible in the numbers and obvious as a picture: a
funnel drawn narrowing to zero then widening again, three campaigns showing
identical revenue, a bundle recommendation resting on two coincidences.

---

## 5. What it is made of

Deliberately boring, because the alternative is a system that stops working when
something upstream changes.

- A tracker on the shop, storing to the shop's own database.
- A signed, one-way export. The shop hands over data; nothing reaches back in.
- A small application that reads that export, plus advertising spend files, and
  keeps the joined history.
- Reports as **self-contained HTML**. Charts drawn by hand as SVG. No chart
  library, no CDN, no script. One file that opens on a phone with no signal,
  prints straight to PDF, and looks identical in two years.
- No dependency on any advertising platform's API. Spend arrives as the CSV
  those platforms already let you download, so the system keeps working when an
  API changes, a token expires or an account loses access.

There is a **manual analysis loop** too. The system can export the whole
consolidated picture as a self-describing pack, which a human analyst or an AI
can read and reply to in a fixed format the system reads back in. That means the
strategic layer works with no API key and no subscription, and the automated
version is the same pack with a network call instead of a person.

---

## 6. Where to point it

The pattern fits anywhere the sale leaves the website. The vocabulary changes;
nothing else does.

| Business | The trail ends when | Profit per order is really |
|---|---|---|
| Chat-first retail | The customer opens WhatsApp | Sale price less supplier, delivery, returns |
| Clinics, salons, garages | The customer books or rings | Visit value less staff time and materials |
| Trades and home services | The customer requests a quote | Job value less labour, materials, travel |
| Cash-on-delivery ecommerce | The order is placed but may be refused | Order value less cost, delivery, refusal rate |
| B2B and high-consideration | A form becomes a sales conversation | Deal value less cost of sale, times close rate |
| Physical shops running ads | The customer walks in | Basket value less cost of goods |
| Education, membership, subscription | Enquiry becomes enrolment | Lifetime value, not first payment |

The two things that must be true:

1. **Something observable happens at the moment the trail ends.** A tap, a click,
   a form, a scan. It does not have to be the sale, it has to be the last
   trackable step.
2. **Somebody eventually knows the sale happened**, even if that is a person
   ticking a box afterwards. The system closes the loop between those two facts.

If both hold, the rest is naming.

---

## 7. Who this is not for

Stated plainly, because a tool sold to the wrong buyer generates support, not
revenue.

- **Shops where checkout completes on the website.** Standard ecommerce
  analytics already sees the sale. This solves a problem you do not have.
- **Anyone spending nothing on advertising.** Most of the value is deciding where
  ad money goes. With no ad spend it is a profit and customer reporting tool,
  which is useful but is not the pitch.
- **Anyone unwilling to enter their costs.** The whole system rests on knowing
  what things actually cost. Nobody else can supply that number, and without it
  every verdict is measured against a guess.
- **Anyone wanting fully automated bidding.** This advises. By design.
- **Very high volume.** The reporting is built for a business a person can hold
  in their head, and it optimises for a decision being obvious rather than for
  the number of rows on a screen.

---

## 8. The honest description

Most analytics tells you what happened. This tells you what to do about it, and
shows the arithmetic so you can disagree.

It knows what it does not know, says so on every page, and refuses to fill a gap
with a flattering number. That restraint is not a limitation of the build. It is
the reason the output is worth reading: a system that will not guess is a system
whose numbers you can act on.
