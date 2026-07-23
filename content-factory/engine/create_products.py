#!/usr/bin/env python3
"""
Create the shop products on shop.websitesgh.com via the WooCommerce REST API.
Idempotent: finds an existing product by SKU and updates it, else creates it.
Reads products/mr-boadi-products.json and config/wp.env.

Auth: tries WP Application Password (Basic auth) first. If WooCommerce rejects it,
fall back to WooCommerce consumer keys: add WC_KEY and WC_SECRET to wp.env
(WooCommerce > Settings > Advanced > REST API > Add key, Read/Write).

Prints each product's id and permalink so you can wire article CTAs to real URLs.
Run:  python create_products.py            (create/update all)
      python create_products.py --dry-run  (show payloads only)
"""
import base64, json, os, sys, urllib.request, urllib.parse, urllib.error

HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE)

def env():
    e = {}
    p = os.path.join(ROOT, "config", "wp.env")
    if not os.path.exists(p): sys.exit("Missing config/wp.env")
    for line in open(p):
        if "=" in line and not line.strip().startswith("#"):
            k, v = line.strip().split("=", 1); e[k.strip()] = v.strip()
    e["WP_BASE_URL"] = e["WP_BASE_URL"].rstrip("/")
    return e

def call(e, method, path, params=None, body=None):
    url = e["WP_BASE_URL"] + "/wp-json/wc/v3" + path
    q = dict(params or {})
    if e.get("WC_KEY") and e.get("WC_SECRET"):
        q.update({"consumer_key": e["WC_KEY"], "consumer_secret": e["WC_SECRET"]})
    if q: url += "?" + urllib.parse.urlencode(q)
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Content-Type", "application/json")
    if not (e.get("WC_KEY") and e.get("WC_SECRET")):
        raw = f'{e["WP_USER"]}:{e["WP_APP_PASSWORD"]}'.encode()
        req.add_header("Authorization", "Basic " + base64.b64encode(raw).decode())
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read().decode() or "null")
    except urllib.error.HTTPError as ex:
        body = ex.read().decode(errors="replace")
        raise SystemExit(f"HTTP {ex.code} {method} {path}: {body[:400]}\n"
                         f"If this is an auth error, add WC_KEY/WC_SECRET to wp.env.")
    except urllib.error.URLError as ex:
        raise SystemExit(f"Network error: {ex}. Is shop.websitesgh.com allowlisted and reachable?")

def cat_id(e, name, cache):
    if name in cache: return cache[name]
    found = call(e, "GET", "/products/categories", params={"search": name, "per_page": 100})
    for c in found or []:
        if c["name"].lower() == name.lower():
            cache[name] = c["id"]; return c["id"]
    created = call(e, "POST", "/products/categories", body={"name": name})
    cache[name] = created["id"]; return created["id"]

def find_by_sku(e, sku):
    found = call(e, "GET", "/products", params={"sku": sku})
    return found[0] if found else None

def main():
    dry = "--dry-run" in sys.argv
    e = env()
    prods = json.load(open(os.path.join(ROOT, "products", "mr-boadi-products.json")))
    cache = {}
    results = []
    for p in prods:
        payload = {
            "name": p["name"], "type": "simple", "sku": p["sku"],
            "regular_price": str(p["price"]),
            "description": p["desc"], "short_description": p["short"],
            "manage_stock": True, "stock_quantity": p["stock"], "stock_status": "instock",
            "weight": str(p.get("weight", "")), "featured": bool(p.get("featured")),
            "tags": [{"name": t} for t in p.get("tags", [])],
        }
        if dry:
            print(f"DRY {p['sku']}: {p['name']} | GHS {p['price']} | stock {p['stock']}")
            continue
        payload["categories"] = [{"id": cat_id(e, c, cache)} for c in p.get("cats", [])]
        existing = find_by_sku(e, p["sku"])
        if existing:
            out = call(e, "PUT", f"/products/{existing['id']}", body=payload); action = "updated"
        else:
            out = call(e, "POST", "/products", body=payload); action = "created"
        results.append((p["sku"], out["id"], out.get("permalink"), action))
        print(f"{action:>7}  #{out['id']}  {p['sku']}  ->  {out.get('permalink')}")
    if results:
        # save a sku->permalink map for wiring article CTAs
        m = {sku: {"id": pid, "permalink": link} for sku, pid, link, _ in results}
        json.dump(m, open(os.path.join(ROOT, "products", "sku-permalinks.json"), "w"), indent=2)
        print("\nSaved products/sku-permalinks.json (use it to update article CTA links).")

if __name__ == "__main__":
    main()
