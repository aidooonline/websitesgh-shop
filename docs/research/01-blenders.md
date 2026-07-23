# Deep research: blenders in Ghana

Research date: 23 July 2026. This is the flagship cluster and the template for the
other six categories. Every figure below is sourced or shows its working.

---

## The finding

**Every blender seller in Ghana and Nigeria advertises "4500W" or "8000W". On a
Ghanaian domestic socket, both numbers are physically impossible.** Nobody in this
market has published that. It is our opening.

### The working

Ghana mains is 230V at 50Hz. Domestic sockets are Type G (13A) and Type D (6A).

| Socket | Amps | Maximum deliverable power |
|---|---|---|
| Type G | 13A | 230 x 13 = **2,990W** |
| Type D | 6A | 230 x 6 = **1,380W** |

An 8,000W load at 230V would draw **34.8 amps**. That is 2.7 times what a Type G
socket can deliver and 5.8 times a Type D. The plug fuse blows long before the motor
reaches that figure. A 4,500W claim needs 19.6A and fails the same test.

### Why the number is on the box

It is a peak or stall figure, not continuous draw. Peak ratings reflect the initial
burst at motor startup rather than sustained blending power, the same trick behind
inflated horsepower claims on premium brands. One horsepower is 746 watts, so the
honest way to read an "8000W" label is that it is a marketing unit, not an
electrical one. Real continuous draw on these units sits between roughly 300W and
1,200W depending on the motor.

### Why this is worth owning

Ask any answer engine "how many watts is a Silver Crest blender really" today and it
has nothing authoritative to quote. Every source repeats the box. We publish the
socket arithmetic, and we become the citation.

---

## Original data asset: running cost in cedis

PURC raised electricity tariffs 3.49 per cent effective 1 July 2026. Applying that to
the Q2 2026 approved residential rates:

| Customer band | Q2 2026 rate | From 1 July 2026 |
|---|---|---|
| Residential 0 to 300 kWh | 196.8825 GHp/kWh | **GHS 2.04 /kWh** |
| Residential 301+ kWh | 260.1481 GHp/kWh | **GHS 2.69 /kWh** |
| Lifeline 0 to 30 kWh | 86.90 GHp/kWh | **89.93 GHp /kWh** |

At GHS 2.04 per kWh, ten minutes of blending a day costs:

| Real continuous draw | kWh per month | Cost per month |
|---|---|---|
| 300W | 1.5 | **GHS 3.06** |
| 600W | 3.0 | **GHS 6.11** |
| 800W | 4.0 | **GHS 8.15** |
| 1200W | 6.0 | **GHS 12.23** |

Nobody in Ghana publishes this table. It answers a question every buyer actually has
and every competitor ignores. Refresh it each quarter when PURC reviews tariffs.

---

## Market pricing, observed 23 July 2026

| Source | Product | Asking price |
|---|---|---|
| Jumia GH | Silver Crest LM-2L commercial | GHS 135 |
| Jiji, Achimota | Silver Crest commercial | GHS 250 |
| Jiji, Kumasi | Silver Crest commercial | GHS 250 to 280 |
| Jiji, Spintex / Roman Ridge | Silver Crest 2 to 2.5L | GHS 380 to 400 |
| Jiji, Adenta | Silver Crest 2-in-1 2L | GHS 350 |
| Jiji, Accra | Silver Crest 8000W 2 cup | GHS 550 |

Spread on the same product class is roughly **4x**. Jiji asking prices are not sold
prices, so treat the top of that range as a ceiling to test, not a guarantee.

Demand proof: the Jumia listing carries **5,550 ratings**, the highest single product
count in its top sellers list. This product sells.

---

## The buyer questions to answer

Taken from how people actually phrase it, not marketing paraphrase:

1. How many watts is a Silver Crest blender really?
2. Can a Silver Crest blender grind dry pepper and grains, or only wet?
3. How much does it cost to run a blender per month in Ghana?
4. Why did my blender stop mid-blend and start again later?
   (Overheat protection tripping. Explain the reset, do not hide it.)
5. Is the 2 litre or the 2.5 litre better for a family of five?
6. Why is the same blender GHS 135 on Jumia and GHS 350 on Jiji?
   (Answer honestly: shipping lead time, local stock, and different motor variants
   sold under one brand name. This question is high intent and nobody answers it.)
7. Will it blend fufu or is a separate pounder needed?
8. What warranty do I actually get in Ghana?

---

## Pages to build from this cluster

| Page | Type | Why |
|---|---|---|
| Ghana blender buying guide | Pillar | Owns the head term |
| What "8000W" really means on a Ghana blender | Data asset | The citation magnet |
| Ghana appliance running cost table | Data asset | Reusable across all 7 categories |
| Silver Crest 2L product page | Product | Money page |
| Silver Crest 2.5L with dry mill | Product | Money page |
| 2L vs 2.5L, which for a Ghanaian kitchen | Comparison | High intent |
| Why blender prices vary 4x in Ghana | Problem | Trust builder, pure original data |

---

## Verification status

| Claim | Status |
|---|---|
| Ghana 230V, Type D and G, 13A and 6A | Verified, multiple sources |
| Socket wattage ceilings | Calculated, working shown |
| PURC Q2 2026 rates and 1 July 3.49 per cent rise | Verified, PURC decision documents |
| Running cost table | Calculated from verified tariffs |
| Market prices | Observed live 23 July 2026 |
| Peak versus continuous wattage | Verified against industry sources |
| **Real measured draw of a specific unit** | **NOT verified. Needs a plug-in power meter on an actual unit before we publish a specific figure.** |

The last row matters. We can prove 8,000W is impossible. We cannot yet publish "this
exact unit draws 620W" without measuring one. Buy a clamp meter or plug-in energy
monitor, measure three units, publish the numbers. That single measurement turns this
from good analysis into the primary source of record.
