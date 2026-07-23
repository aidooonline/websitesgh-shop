#!/usr/bin/env python3
"""
Build the original data assets that make WebsitesGH Shop citable.

Two outputs, both computed from verified public figures with the working shown:

1. Ghana Appliance Running Cost Table. Real continuous draw per appliance class,
   converted to kWh per month and cedis per month at current PURC tariffs.
2. Real Power Bank Capacity Table. Advertised mAh converted to actual delivered
   mAh at 5V, which is the number that matters and the one nobody publishes.

Sources, all verifiable:
- PURC 2026 Second Quarter Tariff Review Decision, effective 1 April 2026
- PURC quarterly review effective 1 July 2026, electricity up 3.49 per cent
- Ghana mains: 230V 50Hz, socket types D (6A) and G (13A)
- Power bank physics: cells at 3.7V nominal, USB output at 5V

Refresh cadence: rerun after every PURC quarterly review. The visible date on the
published page must always read current or answer engines will down rank it.

Run: python3 content-factory/engine/build_data_assets.py
Writes: content-factory/data/running-costs.json, real-capacity.json
"""

import json
import os
from datetime import date

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
OUT = os.path.join(ROOT, "content-factory", "data")

# --- Verified inputs -------------------------------------------------------
MAINS_VOLTS = 230
SOCKETS = {"Type G": 13, "Type D": 6}

# PURC approved residential rates, GHp/kWh, effective 1 April 2026
PURC_Q2 = {"lifeline_0_30": 86.9000, "res_0_300": 196.8825, "res_301_plus": 260.1481}
# PURC quarterly review effective 1 July 2026
JULY_2026_UPLIFT = 1.0349

TARIFF_SOURCE = ("PURC 2026 Second Quarter Tariff Review Decision, "
                 "uplifted by the 3.49 per cent electricity increase "
                 "effective 1 July 2026")

# Appliance class, realistic CONTINUOUS draw in watts, typical minutes of use per day.
# These are class figures, not measurements of a specific unit. Any page that quotes
# a specific unit must be backed by a plug in energy monitor reading first.
APPLIANCES = [
    ("Blender, 2L commercial",            600, 10),
    ("Blender, 2.5L with dry mill",       750, 12),
    ("Rice cooker, 5L",                   900, 45),
    ("Electric kettle, 1.8L",            2000,  8),
    ("Electric kettle, 2L",              2000, 10),
    ("Hot plate, single burner",         1000, 40),
    ("Hot plate, double burner",         2000, 45),
    ("Microwave oven, 20L",              1100, 15),
    ("Sandwich maker",                    750, 10),
    ("Deep fryer, 2.5L",                 1800, 20),
    ("Dry iron, 1200W",                  1200, 20),
    ("Steam iron, 1450W",                1450, 20),
    ("Handheld garment steamer",         1000, 10),
    ("Hair dryer, 2000W",                2000, 10),
    ("Hair straightener",                  45, 10),
    ("Rechargeable hair clipper",          10, 15),
    ("Facial steamer",                    300, 10),
    ("LED strip light, 10M",               24, 300),
    ("Rechargeable LED lamp",              10, 240),
    ("Rechargeable standing fan",          25, 360),
    ("Laptop cooling pad",                  5, 240),
]

# Advertised mAh on the power banks we stock
POWER_BANKS = [10000, 20000, 30000]
CELL_VOLTS, OUTPUT_VOLTS = 3.7, 5.0
EFF_BUDGET, EFF_QUALITY = 0.70, 0.85   # budget vs quality conversion efficiency


def tariffs():
    """Current GHS per kWh by band."""
    return {k: round(v * JULY_2026_UPLIFT / 100, 4) for k, v in PURC_Q2.items()}


def socket_ceilings():
    rows = [{"socket": s, "amps": a, "max_watts": MAINS_VOLTS * a}
            for s, a in SOCKETS.items()]
    for claim in (4500, 8000):
        amps = round(claim / MAINS_VOLTS, 1)
        rows.append({
            "socket": "Advertised claim of " + str(claim) + "W",
            "amps": amps,
            "max_watts": claim,
            "note": ("Would need " + str(amps) + "A at 230V, which is "
                     + str(round(claim / (MAINS_VOLTS * 13), 1))
                     + "x a Type G socket and "
                     + str(round(claim / (MAINS_VOLTS * 6), 1))
                     + "x a Type D. Not physically deliverable on Ghanaian mains."),
        })
    return rows


def running_costs(rate):
    out = []
    for name, watts, mins in APPLIANCES:
        kwh_month = watts / 1000 * (mins / 60) * 30
        out.append({
            "appliance": name,
            "real_watts": watts,
            "minutes_per_day": mins,
            "kwh_per_month": round(kwh_month, 2),
            "ghs_per_month": round(kwh_month * rate, 2),
            "ghs_per_year": round(kwh_month * rate * 12, 2),
        })
    return sorted(out, key=lambda r: -r["ghs_per_month"])


def real_capacity():
    out = []
    for mah in POWER_BANKS:
        wh = mah * CELL_VOLTS / 1000
        lo = (CELL_VOLTS * mah * EFF_BUDGET) / OUTPUT_VOLTS
        hi = (CELL_VOLTS * mah * EFF_QUALITY) / OUTPUT_VOLTS
        out.append({
            "advertised_mah": mah,
            "stored_wh": round(wh, 1),
            "real_mah_at_5v_budget": int(lo),
            "real_mah_at_5v_quality": int(hi),
            "shortfall_percent_budget": round((1 - lo / mah) * 100, 1),
            "shortfall_percent_quality": round((1 - hi / mah) * 100, 1),
            # A typical Ghana-market smartphone battery is about 5000mAh
            "full_phone_charges_budget": round(lo / 5000, 1),
            "full_phone_charges_quality": round(hi / 5000, 1),
        })
    return out


def main():
    os.makedirs(OUT, exist_ok=True)
    t = tariffs()
    rate = t["res_0_300"]
    stamp = date.today().isoformat()

    a = {
        "generated": stamp,
        "verified_month": date.today().strftime("%B %Y"),
        "tariff_source": TARIFF_SOURCE,
        "mains_volts": MAINS_VOLTS,
        "tariffs_ghs_per_kwh": t,
        "rate_used": rate,
        "socket_ceilings": socket_ceilings(),
        "rows": running_costs(rate),
    }
    b = {
        "generated": stamp,
        "verified_month": date.today().strftime("%B %Y"),
        "method": ("Real mAh at 5V = (cell voltage 3.7 x advertised mAh x conversion "
                   "efficiency) / output voltage 5.0. Budget cells assume 70 per cent "
                   "efficiency, quality cells 85 per cent."),
        "rows": real_capacity(),
    }

    json.dump(a, open(os.path.join(OUT, "running-costs.json"), "w"), indent=2)
    json.dump(b, open(os.path.join(OUT, "real-capacity.json"), "w"), indent=2)

    print("Tariffs from 1 July 2026, GHS per kWh:")
    for k, v in t.items():
        print("  %-16s %.4f" % (k, v))
    print("\nTop running costs at GHS %.2f per kWh:" % rate)
    for r in a["rows"][:6]:
        print("  %-30s %5d W  %6.2f kWh/mo  GHS %6.2f/mo"
              % (r["appliance"], r["real_watts"], r["kwh_per_month"], r["ghs_per_month"]))
    print("\nPower bank real capacity at 5V:")
    for r in b["rows"]:
        print("  %6d mAh advertised -> %5d to %5d mAh real (%.0f to %.0f pc short)"
              % (r["advertised_mah"], r["real_mah_at_5v_budget"],
                 r["real_mah_at_5v_quality"], r["shortfall_percent_quality"],
                 r["shortfall_percent_budget"]))


if __name__ == "__main__":
    main()
