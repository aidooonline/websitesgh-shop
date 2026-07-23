# Deep research: power banks, lamps and fans in Ghana

Research date: 23 July 2026.

## The finding

Same shape as the blender wattage lie. **Every power bank in this market is sold on a
mAh number that the device never receives.** A "30,000mAh" bank delivers roughly half
that to your phone. The physics is settled and the arithmetic is trivial, yet no
Ghanaian seller publishes it.

### The working

Power bank cells run at 3.7V nominal. USB output is 5V. A boost converter bridges the
gap and loses energy as heat. Energy is conserved in watt hours, not mAh, so the
advertised mAh is a charge figure at cell voltage, not at output voltage.

    Real mAh at 5V = (3.7 x advertised mAh x efficiency) / 5.0

Quality units run 85 per cent conversion efficiency. Budget units, which is most of
what sells in Accra, run nearer 70 per cent.

| Advertised | Stored | Real at 5V (budget) | Real at 5V (quality) | Shortfall |
|---|---|---|---|---|
| 10,000mAh | 37.0 Wh | 5,180mAh | 6,290mAh | 37 to 48 pc |
| 20,000mAh | 74.0 Wh | 10,360mAh | 12,580mAh | 37 to 48 pc |
| 30,000mAh | 111.0 Wh | 15,540mAh | 18,870mAh | 37 to 48 pc |

Against a typical 5,000mAh phone battery, the honest claim for a "30,000mAh" bank is
**about three to four full charges, not six.** Anker publishes the same maths for its
own products, quoting roughly 82 per cent efficiency and a rated capacity near
6,068mAh on a 10,000mAh bank. The industry knows. The Accra market does not say it.

### Why we publish it anyway

Counterintuitive, but this sells more units, not fewer. Every buyer who has owned a
power bank already knows it underdelivers. Being the one seller who explains why,
with the arithmetic on the page, converts better than another "30000mAh SUPER FAST"
listing. It also makes us the source an answer engine quotes.

---

## Original data asset: what appliances actually cost to run

PURC tariffs effective 1 July 2026, residential 0 to 300 kWh, **GHS 2.04 per kWh**.

| Appliance | Real draw | Use per day | kWh per month | Cost per month |
|---|---|---|---|---|
| Hot plate, double burner | 2000W | 45 min | 45.0 | **GHS 91.69** |
| Rice cooker, 5L | 900W | 45 min | 20.3 | **GHS 41.26** |
| Hot plate, single burner | 1000W | 40 min | 20.0 | **GHS 40.75** |
| Deep fryer, 2.5L | 1800W | 20 min | 18.0 | **GHS 36.68** |
| Steam iron, 1450W | 1450W | 20 min | 14.5 | **GHS 29.54** |
| Dry iron, 1200W | 1200W | 20 min | 12.0 | **GHS 24.45** |
| Blender, 2L | 600W | 10 min | 3.0 | **GHS 6.11** |
| Rechargeable LED lamp | 10W | 4 hr | 1.2 | **GHS 2.44** |

Two sales insights fall straight out of this table:

1. **A double burner hot plate costs GHS 92 a month to run.** That is more than a
   third of the purchase price every single month. Buyers deserve to know, and the
   seller who tells them is the seller they come back to.
2. **The blender is one of the cheapest things in the kitchen to run**, at about
   GHS 6 a month. That is a selling point nobody uses because nobody has done the sum.

Full dataset: `content-factory/data/running-costs.json`. Rerun the generator after
every PURC quarterly review.

---

## The buyer questions to answer

1. How many times will a 30000mAh power bank charge my phone, really?
2. Why does my power bank die faster than the number says?
3. What size power bank do I need for a full day without light?
4. Is a rechargeable fan strong enough to sleep with?
5. How long does a rechargeable lamp last on one charge?
6. Which is cheaper to run, a hot plate or gas?
7. Can I take this power bank on a flight?
   (111Wh on a 30,000mAh unit is over the usual 100Wh carry on limit. Worth saying.)

---

## Pages to build

| Page | Type |
|---|---|
| What 30000mAh really means, with the maths | Data asset, citation magnet |
| Ghana appliance running cost table | Data asset, reused across all categories |
| Power bank buying guide for Ghana | Pillar |
| Best power bank size for your phone | Comparison |
| Each power bank SKU | Product |

## Verification status

| Claim | Status |
|---|---|
| 3.7V cell to 5V output conversion loss | Verified, multiple independent sources |
| 70 to 85 per cent efficiency band | Verified, industry and manufacturer sources |
| Real capacity arithmetic | Calculated, formula shown |
| PURC tariffs and running costs | Verified and calculated |
| Appliance wattages | **Class figures, not unit measurements.** Fine for the general table. Any page quoting a specific SKU needs a plug in energy monitor reading first. |
