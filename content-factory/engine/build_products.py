#!/usr/bin/env python3
"""
Builds the supply into:
  1. products/mr-boadi-products.json  (WooCommerce-ready, same schema as inc/setup-data/products.json)
  2. PRODUCTS-AND-ARTICLES.md         (product -> article map, file paths, publish slots)

Specs are data-backed from manufacturer/retailer sources (2026-07 research).
Prices are PROPOSED (within Stephen's GHS 2,500-3,500 band) and flagged for confirmation.
Stock counts come from mr_boadi.xlsx. "storage_confirm" flags units logged as 0GB storage.
No em dash anywhere.
"""
import json, os

# model -> researched spec block (accurate, 8th gen)
COMMON_INTRO = ("sourced from UK corporate fleets where machines run on strict IT refresh and "
                "maintenance schedules. Every unit is tested, graded and backed by TechPlug GH "
                "warranty, with pickup in Accra and delivery across Ghana.")

def spec_table(rows):
    body = "".join(f"<tr><th>{k}</th><td>{v}</td></tr>" for k, v in rows)
    return f"<table><tbody>{body}</tbody></table>"

def desc(model, blurb, rows):
    return (f"<p>The {model} is a business-class laptop {COMMON_INTRO} {blurb}</p>"
            f"<h3>{model} key specifications</h3>{spec_table(rows)}")

# 8th gen EliteBook shared bits
UHD620 = "Intel UHD Graphics 620"
WIFI = "Intel Wi-Fi (AX201 class), Bluetooth 5"

PRODUCTS = [
    # name, sku, price(proposed), stock, weight, featured, ram, storage, storage_confirm, blurb, rows
    dict(
        name="HP EliteBook 830 G6 - Core i5 8th Gen, 8GB, 256GB SSD",
        sku="TPG-HP-830G6-I5-8-256", price="2600", stock=33, weight="1.33", featured=True,
        cats=["HP Laptops"], storage_confirm=9,
        tags=["HP EliteBook", "EliteBook 830 G6", "Core i5", "13 inch", "UK used"],
        blurb="At 13.3 inches and about 1.33kg it is the compact, highly portable EliteBook, "
              "ideal for students, professionals and anyone who moves around a lot.",
        rows=[("Processor", "Intel Core i5-8265U / i5-8365U, 8th Gen quad-core, up to 4.1 GHz"),
              ("Memory", "8GB DDR4-2400 (1 SODIMM free, upgradable to 32GB)"),
              ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "13.3 inch FHD (1920x1080) IPS anti-glare, 250 nits"),
              ("Graphics", UHD620),
              ("Ports", "Thunderbolt 3 / USB-C, 2x USB-A 3.1, HDMI"),
              ("Wireless", WIFI),
              ("Battery", "50Wh 3-cell, HP Fast Charge (up to 50% in 30 min)"),
              ("Build", "Aluminium chassis, MIL-STD 810G tested, ~1.33 kg"),
              ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 840 G6 - Core i5 8th Gen, 8GB, 256GB SSD",
        sku="TPG-HP-840G6-I5-8-256", price="2600", stock=26, weight="1.48", featured=True,
        cats=["HP Laptops"], storage_confirm=1,
        tags=["HP EliteBook", "EliteBook 840 G6", "Core i5", "14 inch", "UK used"],
        blurb="The 14 inch all-rounder with a slim bezel, full-size backlit keyboard and a "
              "bright anti-glare screen. The most popular business laptop size in Ghana.",
        rows=[("Processor", "Intel Core i5-8265U / i5-8365U, 8th Gen quad-core, up to 4.1 GHz"),
              ("Memory", "8GB DDR4-2400 (upgradable to 64GB)"),
              ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) IPS anti-glare, 250 nits"),
              ("Graphics", UHD620),
              ("Ports", "Thunderbolt 3 / USB-C, 2x USB-A 3.1, HDMI, RJ45 Ethernet"),
              ("Wireless", "Intel Wi-Fi 6 AX201, Bluetooth 5"),
              ("Battery", "50Wh, HP Fast Charge"),
              ("Build", "Aluminium unibody, MIL-STD 810G tested"),
              ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 840 G6 - Core i5 8th Gen, 16GB, 256GB SSD",
        sku="TPG-HP-840G6-I5-16-256", price="2850", stock=2, weight="1.48", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP EliteBook", "EliteBook 840 G6", "Core i5", "16GB", "UK used"],
        blurb="Same proven 14 inch chassis with 16GB RAM for heavier multitasking, many "
              "browser tabs and virtual machines.",
        rows=[("Processor", "Intel Core i5-8265U / i5-8365U, 8th Gen quad-core, up to 4.1 GHz"),
              ("Memory", "16GB DDR4-2400"),
              ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) IPS anti-glare, 250 nits"),
              ("Graphics", UHD620), ("Wireless", "Intel Wi-Fi 6 AX201, Bluetooth 5"),
              ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 840 G6 - Core i5 8th Gen, 32GB, 256GB SSD",
        sku="TPG-HP-840G6-I5-32-256", price="3100", stock=1, weight="1.48", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP EliteBook", "EliteBook 840 G6", "32GB", "UK used"],
        blurb="A rare 32GB configuration for developers, analysts and power users who run "
              "memory-hungry workloads. Single unit in stock.",
        rows=[("Processor", "Intel Core i5-8265U / i5-8365U, 8th Gen quad-core, up to 4.1 GHz"),
              ("Memory", "32GB DDR4-2400"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) IPS anti-glare"), ("Graphics", UHD620),
              ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 840 G5 - Core i5 8th Gen, 8GB, 256GB SSD",
        sku="TPG-HP-840G5-I5-8-256", price="2500", stock=11, weight="1.48", featured=True,
        cats=["HP Laptops"], storage_confirm=1,
        tags=["HP EliteBook", "EliteBook 840 G5", "Core i5", "14 inch", "UK used"],
        blurb="The value 14 inch EliteBook. Same durable build and 8th gen performance as the "
              "G6, at the friendliest price in the range.",
        rows=[("Processor", "Intel Core i5-8250U / i5-8350U, 8th Gen quad-core, up to 3.6 GHz"),
              ("Memory", "8GB DDR4-2400 (upgradable to 32GB)"),
              ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) IPS anti-glare, 220 nits"),
              ("Graphics", UHD620),
              ("Ports", "USB-C, 2x USB-A 3.1, HDMI, RJ45 Ethernet, microSD"),
              ("Wireless", WIFI), ("Keyboard", "Spill-resistant backlit keyboard"),
              ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 840 G5 - Core i5 8th Gen, 16GB, 256GB SSD",
        sku="TPG-HP-840G5-I5-16-256", price="2750", stock=9, weight="1.48", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP EliteBook", "EliteBook 840 G5", "16GB", "14 inch", "UK used"],
        blurb="16GB of RAM for smoother multitasking, at a price well below the newer "
              "generations. A strong value pick for business and study.",
        rows=[("Processor", "Intel Core i5-8250U / i5-8350U, 8th Gen quad-core, up to 3.6 GHz"),
              ("Memory", "16GB DDR4-2400"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) IPS anti-glare"), ("Graphics", UHD620),
              ("Wireless", WIFI), ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP ProBook 445r G6 - Ryzen 5, 8GB, 256GB SSD",
        sku="TPG-HP-445RG6-R5-8-256", price="2500", stock=3, weight="1.6", featured=False,
        cats=["HP Laptops"], storage_confirm=1,
        tags=["HP ProBook", "ProBook 445r G6", "Ryzen 5", "AMD", "UK used"],
        blurb="AMD Ryzen 5 value laptop with strong integrated Radeon graphics, good for "
              "everyday work, study and light creative tasks.",
        rows=[("Processor", "AMD Ryzen 5 3500U, 4 cores / 8 threads, up to 3.7 GHz"),
              ("Memory", "8GB DDR4 (upgradable)"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) anti-glare"),
              ("Graphics", "AMD Radeon Vega 8 integrated"),
              ("Ports", "USB-C, USB-A 3.1, HDMI, RJ45 Ethernet"),
              ("Wireless", WIFI), ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP ProBook 445 G7 - Ryzen 5, 8GB, 256GB SSD",
        sku="TPG-HP-445G7-R5-8-256", price="2600", stock=1, weight="1.6", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP ProBook", "ProBook 445 G7", "Ryzen 5", "AMD", "UK used"],
        blurb="Newer ProBook chassis with Ryzen 5 performance and Radeon graphics. Single "
              "unit in stock.",
        rows=[("Processor", "AMD Ryzen 5 4500U, 6 cores, up to 4.0 GHz"),
              ("Memory", "8GB DDR4"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) anti-glare"),
              ("Graphics", "AMD Radeon integrated"), ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 745 G6 - Ryzen 5 Pro, 16GB, 256GB SSD",
        sku="TPG-HP-745G6-R5P-16-256", price="2750", stock=1, weight="1.5", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP EliteBook", "EliteBook 745 G6", "Ryzen 5 Pro", "16GB", "UK used"],
        blurb="Enterprise-grade AMD EliteBook with 16GB RAM and Ryzen Pro security features. "
              "Single unit in stock.",
        rows=[("Processor", "AMD Ryzen 5 Pro 3500U, 4 cores / 8 threads"),
              ("Memory", "16GB DDR4"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "14 inch FHD (1920x1080) IPS anti-glare"),
              ("Graphics", "AMD Radeon Vega integrated"), ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP EliteBook 830 G5 - Core i5 8th Gen, 16GB, 256GB SSD",
        sku="TPG-HP-830G5-I5-16-256", price="2600", stock=1, weight="1.33", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP EliteBook", "EliteBook 830 G5", "Core i5", "16GB", "13 inch", "UK used"],
        blurb="Compact 13.3 inch EliteBook with 16GB RAM. Portable and capable. Single unit.",
        rows=[("Processor", "Intel Core i5-8250U / i5-8350U, 8th Gen quad-core"),
              ("Memory", "16GB DDR4-2400"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "13.3 inch FHD (1920x1080) IPS anti-glare"), ("Graphics", UHD620),
              ("OS", "Windows 10 / 11 Pro")]),
    dict(
        name="HP 250 G7 - Core i5 8th Gen, 8GB, 256GB SSD",
        sku="TPG-HP-250G7-I5-8-256", price="2500", stock=1, weight="1.78", featured=False,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP 250 G7", "Core i5", "15 inch", "UK used"],
        blurb="15.6 inch everyday laptop with a full-size keyboard and number pad. Good for "
              "home, office and study. Single unit in stock.",
        rows=[("Processor", "Intel Core i5-8265U, 8th Gen quad-core"),
              ("Memory", "8GB DDR4"), ("Storage", "256GB NVMe / M.2 SSD"),
              ("Display", "15.6 inch FHD (1920x1080)"), ("Graphics", UHD620),
              ("OS", "Windows 10 / 11")]),
    dict(
        name="HP ZBook 15 G5 Mobile Workstation - Core i7 8th Gen, 16GB, 512GB SSD, Quadro",
        sku="TPG-HP-ZB15G5-I7-16-512", price="3500", stock=1, weight="2.6", featured=True,
        cats=["HP Laptops"], storage_confirm=0,
        tags=["HP ZBook", "ZBook 15 G5", "mobile workstation", "Core i7", "Quadro", "UK used"],
        blurb="A true mobile workstation for engineering, CAD, 3D, video editing and heavy "
              "development. 6-core i7, dedicated NVIDIA Quadro graphics, 16GB RAM and a fast "
              "512GB SSD. The most powerful unit in this batch.",
        rows=[("Processor", "Intel Core i7-8750H, 8th Gen 6-core, up to 4.1 GHz, 9MB cache"),
              ("Memory", "16GB DDR4-2667 (up to 32GB, dual channel)"),
              ("Storage", "512GB NVMe / M.2 SSD (second bay available)"),
              ("Display", "15.6 inch FHD (1920x1080) IPS anti-glare"),
              ("Graphics", "NVIDIA Quadro P1000 / P2000 (4GB GDDR5) + Intel UHD 630"),
              ("Ports", "Thunderbolt 3, HDMI 2.0, USB-A, RJ45, SD reader"),
              ("Wireless", "Intel Wi-Fi + Bluetooth"),
              ("Build", "Workstation chassis, HP Sure Start BIOS, ~2.6 kg"),
              ("OS", "Windows 10 / 11 Pro")]),
]

def slugify(name):
    base = name.split(" - ")[0].lower().replace("hp ", "hp-")
    for a, b in [(" ", "-"), ("(", ""), (")", ""), (".", ""), ("/", "-")]:
        base = base.replace(a, b)
    while "--" in base:
        base = base.replace("--", "-")
    return base.strip("-")

def build():
    out = []
    for p in PRODUCTS:
        model = p["name"].split(" - ")[0]
        short_bits = []
        for k, v in p["rows"]:
            if k in ("Processor", "Memory", "Storage", "Display"):
                short_bits.append(v.split(",")[0].split("(")[0].strip())
        short = " &middot; ".join(short_bits[:4]) + " &middot; UK used, tested &amp; warranty-backed."
        out.append({
            "sku": p["sku"], "name": p["name"], "price": p["price"], "stock": p["stock"],
            "weight": p["weight"], "featured": p["featured"], "short": short,
            "desc": desc(model, p["blurb"], p["rows"]),
            "cats": p["cats"], "tags": p["tags"], "image": "",
            "_storage_confirm_units": p["storage_confirm"],
        })
    return out

if __name__ == "__main__":
    here = os.path.dirname(os.path.abspath(__file__))
    root = os.path.dirname(here)
    os.makedirs(os.path.join(root, "products"), exist_ok=True)
    prods = build()
    # separate the internal flag out of the WooCommerce payload
    clean = [{k: v for k, v in p.items() if not k.startswith("_")} for p in prods]
    json.dump(clean, open(os.path.join(root, "products", "mr-boadi-products.json"), "w"),
              indent=2, ensure_ascii=False)
    # guard: no em dash
    blob = json.dumps(clean, ensure_ascii=False)
    assert "\u2014" not in blob, "em dash found"
    print(f"Wrote {len(clean)} products, {sum(p['stock'] for p in clean)} total units.")
    flagged = sum(p["_storage_confirm_units"] for p in prods)
    print(f"Units with storage to confirm (logged 0GB): {flagged}")
    for p in prods:
        print(f"  {slugify(p['name']):<40} stock {p['stock']:>2}  GHS {p['price']}"
              + ("  [!storage x%d]" % p["_storage_confirm_units"] if p["_storage_confirm_units"] else ""))
