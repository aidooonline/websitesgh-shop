#!/usr/bin/env python3
"""
Build the placeholder seed catalogue for WebsitesGH Shop.

Every price in here is a PLACEHOLDER derived from observed Ghana market asking
prices, not a confirmed dealer quote. Replace each one after the dealer calls.
Prices are editable in WordPress under Products, so this file never needs to be
edited again once the store is seeded.

Descriptions are written to the GEO standard in content-factory/GEO-DATA-LAYER.md:
a Quick Answer capsule first, then specifics, then an engineered question block.

Run: python3 content-factory/engine/build_seed_catalogue.py
Writes: inc/setup-data/products.json and inc/setup-data/categories.json
"""

import json
import os
import re

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
OUT = os.path.join(ROOT, "inc", "setup-data")

CATEGORIES = [
    ("Kitchen & Home", "kitchen-home",
     "Blenders, rice cookers, kettles and hot plates for the Ghanaian kitchen, delivered in Accra."),
    ("Laundry & Garment Care", "laundry-garment-care",
     "Irons, steamers and drying racks. Pay on delivery across Accra."),
    ("Phones & Audio", "phones-audio",
     "Power banks, earbuds, speakers and charging accessories."),
    ("Computing", "computing",
     "Mice, keyboards, flash drives and laptop accessories."),
    ("Personal Care", "personal-care",
     "Clippers, dryers, straighteners and grooming tools."),
    ("School & Bags", "school-bags",
     "School backpack sets, lunch bags and laptop bags."),
    ("Lighting & Power", "lighting-power",
     "Rechargeable lamps, LED strips, fans and extension boards."),
]

# name, price (GHS placeholder), weight kg, category, short spec line, tags
ITEMS = [
    # Kitchen & Home
    ("Silver Crest 2L Commercial Blender", 280, "3.2", "Kitchen & Home",
     "2 litre jar, stainless blades, wet and dry grinding", ["blender", "silver crest", "kitchen"]),
    ("Silver Crest 2.5L Blender with Dry Mill", 350, "3.8", "Kitchen & Home",
     "2.5 litre jar plus separate dry mill cup", ["blender", "silver crest", "dry mill"]),
    ("5L Digital Rice Cooker", 320, "3.5", "Kitchen & Home",
     "5 litre capacity, 900W, digital timer, keep warm", ["rice cooker", "kitchen"]),
    ("1.8L Stainless Steel Electric Kettle", 120, "1.1", "Kitchen & Home",
     "1.8 litre, stainless body, auto shut off", ["kettle", "kitchen"]),
    ("2L Electric Kettle", 110, "1.2", "Kitchen & Home",
     "2 litre, fast boil, boil dry protection", ["kettle", "kitchen"]),
    ("Electric Hot Plate, Single Burner", 180, "2.0", "Kitchen & Home",
     "Single burner, adjustable heat, 1000W", ["hot plate", "cooker"]),
    ("Electric Hot Plate, Double Burner", 320, "3.4", "Kitchen & Home",
     "Two burners, independent controls, 2000W", ["hot plate", "cooker"]),
    ("20L Microwave Oven with Timer", 950, "11.0", "Kitchen & Home",
     "20 litre, 700W, mechanical timer", ["microwave", "kitchen"]),
    ("Sandwich and Toast Maker", 180, "1.4", "Kitchen & Home",
     "Non stick plates, ready indicator light", ["sandwich maker", "kitchen"]),
    ("2.5L Electric Deep Fryer", 350, "2.6", "Kitchen & Home",
     "2.5 litre, adjustable thermostat, viewing window", ["fryer", "kitchen"]),
    ("7 Piece Non Stick Cookware Set", 420, "5.5", "Kitchen & Home",
     "Seven pieces, non stick coating, glass lids", ["cookware", "pots"]),
    ("Insulated Food Flask and Lunch Set", 150, "0.9", "Kitchen & Home",
     "Stainless flask, carry bag, cutlery", ["lunch box", "flask"]),
    # Laundry & Garment Care
    ("1200W Dry Iron, Non Stick Sole Plate", 190, "1.3", "Laundry & Garment Care",
     "1200W, non stick sole plate, adjustable dial", ["iron", "laundry"]),
    ("1450W Steam Iron", 230, "1.5", "Laundry & Garment Care",
     "1450W, steam burst, spray function", ["steam iron", "laundry"]),
    ("Handheld Garment Steamer", 260, "1.0", "Laundry & Garment Care",
     "Portable, heats in 30 seconds, 200ml tank", ["steamer", "laundry"]),
    ("Foldable Clothes Drying Rack", 180, "3.0", "Laundry & Garment Care",
     "Foldable steel frame, indoor and outdoor", ["drying rack", "laundry"]),
    ("Rechargeable Lint Remover", 90, "0.3", "Laundry & Garment Care",
     "USB rechargeable, removable blade guard", ["lint remover", "laundry"]),
    # Phones & Audio
    ("10000mAh Power Bank with LCD", 130, "0.25", "Phones & Audio",
     "10000mAh, LCD charge display, dual output", ["power bank", "charging"]),
    ("20000mAh Fast Charge Power Bank", 210, "0.42", "Phones & Audio",
     "20000mAh, fast charge, dual USB output", ["power bank", "charging"]),
    ("30000mAh Power Bank with 4 Built In Cables", 320, "0.62", "Phones & Audio",
     "30000mAh, four built in cables, LED torch", ["power bank", "charging"]),
    ("Pro TWS Wireless Earbuds", 90, "0.06", "Phones & Audio",
     "Bluetooth 5.3, charging case, touch control", ["earbuds", "bluetooth"]),
    ("Bluetooth Headset with Charging Case", 120, "0.08", "Phones & Audio",
     "Bluetooth 5.3, finger touch control, case", ["earbuds", "bluetooth"]),
    ("Portable Bluetooth Speaker", 240, "0.55", "Phones & Audio",
     "Bluetooth, USB and SD playback, FM radio", ["speaker", "bluetooth"]),
    ("20W USB C Fast Charger", 70, "0.09", "Phones & Audio",
     "20W power delivery, USB C output", ["charger", "accessories"]),
    ("3 in 1 Fast Charging Cable", 45, "0.07", "Phones & Audio",
     "USB C, micro USB and lightning in one cable", ["cable", "accessories"]),
    ("Dashboard Car Phone Holder", 60, "0.18", "Phones & Audio",
     "Dashboard mount, anti slip pad, adjustable", ["car holder", "accessories"]),
    ("Phone Ring Light with Tripod", 180, "0.75", "Phones & Audio",
     "Ring light, adjustable tripod, phone clamp", ["ring light", "content"]),
    # Computing
    ("2.4G Rechargeable Wireless Mouse", 75, "0.09", "Computing",
     "2.4G wireless, rechargeable, silent click", ["mouse", "computing"]),
    ("Wireless Keyboard and Mouse Combo", 190, "0.65", "Computing",
     "Full size keyboard and mouse, one receiver", ["keyboard", "computing"]),
    ("128GB 2 in 1 Metal Flash Drive", 140, "0.03", "Computing",
     "128GB, USB and OTG adapter, metal body", ["flash drive", "storage"]),
    ("64GB USB Flash Drive", 85, "0.03", "Computing",
     "64GB, USB 2.0, metal casing", ["flash drive", "storage"]),
    ("Adjustable Aluminium Laptop Stand", 150, "0.85", "Computing",
     "Aluminium, adjustable height, foldable", ["laptop stand", "computing"]),
    ("4 Port USB Hub", 80, "0.08", "Computing",
     "Four USB ports, plug and play", ["usb hub", "computing"]),
    ("1080p USB Webcam with Microphone", 220, "0.16", "Computing",
     "1080p, built in microphone, clip mount", ["webcam", "computing"]),
    ("Laptop Cooling Pad with Fans", 180, "0.9", "Computing",
     "Dual fans, adjustable stand, USB powered", ["cooling pad", "computing"]),
    # Personal Care
    ("Rechargeable Metal Hair Clipper", 95, "0.35", "Personal Care",
     "Rechargeable, metal body, guard combs", ["clipper", "grooming"]),
    ("2000W Professional Hair Dryer", 220, "0.7", "Personal Care",
     "2000W, two speeds, cool shot, concentrator", ["hair dryer", "grooming"]),
    ("Ceramic Hair Straightener", 190, "0.45", "Personal Care",
     "Ceramic plates, adjustable heat, fast warm up", ["straightener", "grooming"]),
    ("Rechargeable Electric Shaver", 160, "0.28", "Personal Care",
     "Rechargeable, washable head, travel lock", ["shaver", "grooming"]),
    ("Facial Steamer", 200, "0.6", "Personal Care",
     "Nano mist, 70ml tank, auto shut off", ["facial steamer", "skincare"]),
    ("Digital Bathroom Scale", 150, "1.4", "Personal Care",
     "Tempered glass, 180kg capacity, LED display", ["scale", "health"]),
    # School & Bags
    ("3 in 1 School Backpack Set", 220, "1.1", "School & Bags",
     "Backpack, lunch bag and pencil case", ["school bag", "backpack"]),
    ("5 in 1 School Backpack Set", 250, "1.5", "School & Bags",
     "Backpack, bottle, lunch bag, ice pack and tag", ["school bag", "backpack"]),
    ("Kids Cartoon Backpack, Lower Primary", 130, "0.6", "School & Bags",
     "Lightweight, padded straps, waterproof fabric", ["school bag", "kids"]),
    ("Anti Theft Laptop Backpack with USB Port", 180, "0.95", "School & Bags",
     "Hidden zips, USB charge port, laptop sleeve", ["backpack", "laptop bag"]),
    ("Insulated Lunch Bag and Bottle Set", 140, "0.5", "School & Bags",
     "Insulated bag with matching water bottle", ["lunch bag", "kids"]),
    # Lighting & Power
    ("Rechargeable LED Emergency Lamp", 160, "0.8", "Lighting & Power",
     "Rechargeable, multi brightness, hanging hook", ["lamp", "power cut"]),
    ("10M RGB LED Strip Light with App Control", 120, "0.4", "Lighting & Power",
     "10 metres, app and remote control, colour change", ["led strip", "lighting"]),
    ("Solar Rechargeable Standing Fan", 380, "3.6", "Lighting & Power",
     "Rechargeable, solar input, LED light", ["fan", "solar"]),
    ("4 Socket Surge Protected Extension Board", 110, "0.7", "Lighting & Power",
     "Four sockets, surge protection, 2 metre cable"
     , ["extension", "power"]),
]


def slugify(text):
    s = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
    return s


def sku_for(name, cat):
    prefix = "".join(w[0] for w in cat.replace("&", "").split() if w)[:3].upper()
    return "WGH-" + prefix + "-" + slugify(name)[:28].upper().replace("-", "")[:16]


def description(name, price, short, cat):
    """GEO shaped description: Quick Answer capsule, then detail, then Q and A."""
    return (
        "<p><strong>Quick answer.</strong> The {name} sells for GHS {price} at "
        "WebsitesGH Shop, delivered anywhere in Accra with pay on delivery. "
        "{short}. Order on the site or on WhatsApp and pay the rider when it "
        "reaches you.</p>"
        "<h3>What you get</h3>"
        "<p>{short}. Supplied new in box. We hold stock with Accra dealers and "
        "dispatch the same day on orders confirmed before 4pm, so you are not "
        "waiting weeks for a shipment to arrive.</p>"
        "<h3>Delivery and payment</h3>"
        "<p>Same day delivery in Accra and Tema on confirmed orders. Two to four "
        "working days nationwide. Pay on delivery, or by mobile money and bank "
        "transfer if you prefer. You check the item before you pay.</p>"
        "<h3>Questions people ask</h3>"
        "<h4>How much is the {name} in Ghana?</h4>"
        "<p>GHS {price} at WebsitesGH Shop, including delivery within Accra. "
        "Price verified for the current month and updated whenever our dealer "
        "cost changes.</p>"
        "<h4>Do I pay before delivery?</h4>"
        "<p>No. Pay on delivery is the default. The rider brings the item, you "
        "inspect it, then you pay. Mobile money and bank transfer are available "
        "if you would rather pay ahead.</p>"
        "<h4>How long does delivery take in Accra?</h4>"
        "<p>Same day for orders confirmed before 4pm, otherwise next morning. "
        "Nationwide delivery takes two to four working days.</p>"
    ).format(name=name, price=price, short=short)


def main():
    cats = [{"name": n, "slug": s, "desc": d, "image": ""} for n, s, d in CATEGORIES]

    products = []
    for i, (name, price, weight, cat, short, tags) in enumerate(ITEMS):
        products.append({
            "sku": sku_for(name, cat) + "-" + str(i + 1).zfill(2),
            "name": name,
            "price": str(price),
            "stock": 10,
            "weight": weight,
            "featured": i < 8,
            "short": short + ". Pay on delivery in Accra.",
            "desc": description(name, price, short, cat),
            "cats": [cat],
            "tags": tags,
            "image": "",
        })

    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(OUT, "categories.json"), "w", encoding="utf-8") as f:
        json.dump(cats, f, indent=2, ensure_ascii=False)
    with open(os.path.join(OUT, "products.json"), "w", encoding="utf-8") as f:
        json.dump(products, f, indent=2, ensure_ascii=False)

    print("categories:", len(cats))
    print("products:", len(products))
    lo = min(int(p["price"]) for p in products)
    hi = max(int(p["price"]) for p in products)
    print("price range: GHS", lo, "to", hi)
    skus = [p["sku"] for p in products]
    print("unique SKUs:", len(set(skus)) == len(skus))


if __name__ == "__main__":
    main()
