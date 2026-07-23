#!/usr/bin/env python3
"""
TechPlug GH content-factory publisher.

Publishes an article HTML file to shop.websitesgh.com over the WordPress REST API using
an Application Password. Idempotent (finds an existing post by slug and updates it,
never double-posts), supports back-dating and future-scheduling, and sets category,
tags, excerpt, and publish date.

Auth: HTTP Basic with WP_USER + WP_APP_PASSWORD (WordPress Application Password).
No secret is stored in this file. Reads config/wp.env.

Usage:
    python publish.py --slug hp-elitebook-830-g6-price-in-ghana \
        --title "HP EliteBook 830 G6 Price in Ghana" \
        --html articles/hp-elitebook-830-g6-price-in-ghana.html \
        --date 2026-07-15T09:00:00 \
        --status publish \
        --category "Laptops" --tags "HP EliteBook,EliteBook 830 G6,Price"

    # dry run (build the payload, do not send)
    python publish.py ... --dry-run

Standing rules honored:
  - never claim done without verifying: after publish it re-fetches the post and
    confirms it is live and the slug matches, printing the live URL.
  - no em dash anywhere in this file.
"""
import argparse, base64, json, os, sys, urllib.request, urllib.error, urllib.parse

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)  # content-factory/


def load_env():
    path = os.path.join(ROOT, "config", "wp.env")
    if not os.path.exists(path):
        sys.exit("Missing config/wp.env. Copy config/wp.env.example to config/wp.env and fill it in.")
    env = {}
    for line in open(path):
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip()
    for req in ("WP_BASE_URL", "WP_USER", "WP_APP_PASSWORD"):
        if not env.get(req):
            sys.exit(f"wp.env is missing {req}")
    env["WP_BASE_URL"] = env["WP_BASE_URL"].rstrip("/")
    return env


def auth_header(env):
    raw = f'{env["WP_USER"]}:{env["WP_APP_PASSWORD"]}'.encode()
    return "Basic " + base64.b64encode(raw).decode()


def api(env, method, path, params=None, body=None):
    url = env["WP_BASE_URL"] + "/wp-json/wp/v2" + path
    if params:
        url += "?" + urllib.parse.urlencode(params)
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Authorization", auth_header(env))
    req.add_header("Content-Type", "application/json")
    req.add_header("Accept", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=45) as r:
            return r.status, json.loads(r.read().decode() or "null")
    except urllib.error.HTTPError as e:
        detail = e.read().decode(errors="replace")
        raise SystemExit(f"HTTP {e.code} on {method} {path}: {detail[:500]}")
    except urllib.error.URLError as e:
        raise SystemExit(f"Network error on {method} {path}: {e}. "
                         f"Is {env['WP_BASE_URL']} reachable / allowlisted?")


def term_id(env, taxonomy, name):
    """Find or create a category/tag by name. taxonomy: 'categories' or 'tags'."""
    _, found = api(env, "GET", f"/{taxonomy}", params={"search": name, "per_page": 100})
    for t in found or []:
        if t["name"].lower() == name.lower():
            return t["id"]
    _, created = api(env, "POST", f"/{taxonomy}", body={"name": name})
    return created["id"]


def find_by_slug(env, slug):
    _, posts = api(env, "GET", "/posts", params={"slug": slug, "status": "publish,future,draft"})
    return posts[0] if posts else None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--slug", required=True)
    ap.add_argument("--title", required=True)
    ap.add_argument("--html", required=True, help="path to article HTML body")
    ap.add_argument("--date", required=True, help="ISO local time, e.g. 2026-07-15T09:00:00")
    ap.add_argument("--status", default="publish", choices=["publish", "future", "draft"])
    ap.add_argument("--category", default="")
    ap.add_argument("--tags", default="", help="comma separated")
    ap.add_argument("--excerpt", default="")
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    html_path = a.html if os.path.isabs(a.html) else os.path.join(ROOT, a.html)
    if not os.path.exists(html_path):
        sys.exit(f"Article HTML not found: {html_path}")
    content = open(html_path, encoding="utf-8").read()
    if "\u2014" in content or "\u2014" in a.title:
        sys.exit("Em dash (U+2014) found. Remove it before publishing (standing rule).")

    env = load_env()
    payload = {
        "title": a.title,
        "slug": a.slug,
        "content": content,
        "status": a.status,
        "date": a.date,           # local site time; WP schedules future, back-dates past
        "excerpt": a.excerpt,
    }

    if a.dry_run:
        preview = dict(payload); preview["content"] = f"<{len(content)} chars>"
        print("DRY RUN payload:\n" + json.dumps(preview, indent=2))
        print(f"category={a.category!r} tags={a.tags!r}")
        return

    if a.category:
        payload["categories"] = [term_id(env, "categories", a.category)]
    if a.tags:
        payload["tags"] = [term_id(env, "tags", t.strip()) for t in a.tags.split(",") if t.strip()]

    existing = find_by_slug(env, a.slug)
    if existing:
        status, post = api(env, "POST", f"/posts/{existing['id']}", body=payload)
        action = "UPDATED"
    else:
        status, post = api(env, "POST", "/posts", body=payload)
        action = "CREATED"

    # verify (never claim done without checking the live result)
    _, check = api(env, "GET", f"/posts/{post['id']}")
    live = check.get("link")
    ok = check.get("slug") == a.slug
    print(json.dumps({
        "action": action, "id": post["id"], "slug": check.get("slug"),
        "status": check.get("status"), "date": check.get("date"),
        "link": live, "verified": ok
    }, indent=2))
    if not ok:
        sys.exit("Verification failed: slug mismatch. Investigate before marking done.")


if __name__ == "__main__":
    main()
