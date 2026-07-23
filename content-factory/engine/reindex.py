#!/usr/bin/env python3
"""
Rebuild content-index.md from live WordPress so the index never goes stale
(a stale index is what caused a real duplication incident on another site).
Usage: python reindex.py
"""
import base64, json, os, urllib.request, urllib.parse, urllib.error

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)


def load_env():
    env = {}
    p = os.path.join(ROOT, "config", "wp.env")
    if os.path.exists(p):
        for line in open(p):
            if "=" in line and not line.strip().startswith("#"):
                k, v = line.strip().split("=", 1); env[k.strip()] = v.strip()
    return env


def main():
    env = load_env()
    if not env.get("WP_BASE_URL"):
        raise SystemExit("wp.env not configured.")
    base = env["WP_BASE_URL"].rstrip("/")
    hdr = {}
    if env.get("WP_USER"):
        raw = f'{env["WP_USER"]}:{env["WP_APP_PASSWORD"]}'.encode()
        hdr["Authorization"] = "Basic " + base64.b64encode(raw).decode()
    rows, page = [], 1
    while True:
        url = base + "/wp-json/wp/v2/posts?" + urllib.parse.urlencode(
            {"per_page": 100, "page": page, "status": "publish,future,draft",
             "orderby": "date", "order": "asc"})
        req = urllib.request.Request(url, headers=hdr)
        try:
            with urllib.request.urlopen(req, timeout=45) as r:
                batch = json.loads(r.read().decode() or "[]")
        except urllib.error.HTTPError as e:
            if e.code == 400:
                break
            raise
        if not batch:
            break
        for p in batch:
            rows.append((p["id"], p["slug"], p["status"], p["date"][:10],
                         p["title"]["rendered"], p["link"]))
        page += 1

    out = ["# Content index (rebuilt from live WP)\n",
           "| WP id | slug | status | date | title | url |",
           "|---|---|---|---|---|---|"]
    for r in rows:
        out.append(f"| {r[0]} | {r[1]} | {r[2]} | {r[3]} | {r[4]} | {r[5]} |")
    open(os.path.join(ROOT, "content-index.md"), "w", encoding="utf-8").write("\n".join(out) + "\n")
    print(f"Reindexed {len(rows)} posts into content-index.md")


if __name__ == "__main__":
    main()
