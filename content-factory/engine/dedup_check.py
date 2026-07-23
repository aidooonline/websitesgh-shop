#!/usr/bin/env python3
"""
Dedup gate (locked rule). Before writing any article, run BOTH:
  (a) a grep of content-index.md, and
  (b) a live WP REST check,
but judge duplication by DISTINCTIVE SLUG TOKENS, not raw keyword overlap. On a site where
every post is about laptops in Ghana, a plain full-text search matches "laptop"/"ghana"/"price"
on every post and produces false positives. This gate strips domain-generic words and only flags
a real topic collision (e.g. a second "830 g6 price" page), so the rule stays meaningful.

Usage:
    python dedup_check.py --slug hp-elitebook-830-g6-price-in-ghana ["extra phrase" ...]
    python dedup_check.py "phrase only"   (legacy: treats the phrase as the slug source)
Exit 0 = clear to write. Exit 1 = likely duplicate (update in place instead).
"""
import base64, json, os, sys, urllib.request, urllib.parse, urllib.error

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
INDEX = os.path.join(ROOT, "content-index.md")

# Words that are generic across this whole catalogue and carry no topic signal on their own.
STOP = {
    "laptop", "laptops", "ghana", "gh", "price", "prices", "in", "the", "a", "an", "for",
    "and", "buy", "guide", "2026", "pc", "notebook", "cost", "how", "much", "to", "of",
    "with", "your", "you", "is", "are", "best", "explained", "what", "it",
}


def toks(s):
    out = []
    for w in s.lower().replace("_", "-").replace("/", "-").split("-"):
        w = "".join(c for c in w if c.isalnum())
        if w and w not in STOP:
            out.append(w)
    return set(out)


def load_env():
    path = os.path.join(ROOT, "config", "wp.env")
    env = {}
    if os.path.exists(path):
        for line in open(path):
            if "=" in line and not line.strip().startswith("#"):
                k, v = line.strip().split("=", 1)
                env[k.strip()] = v.strip()
    return env


def all_posts(env):
    """Return [(slug, title, status, link)] for every post (published/future/draft)."""
    if not env.get("WP_BASE_URL"):
        return None
    base = env["WP_BASE_URL"].rstrip("/")
    hdr = {}
    if env.get("WP_USER") and env.get("WP_APP_PASSWORD"):
        raw = f'{env["WP_USER"]}:{env["WP_APP_PASSWORD"]}'.encode()
        hdr["Authorization"] = "Basic " + base64.b64encode(raw).decode()
    rows, page = [], 1
    while True:
        url = base + "/wp-json/wp/v2/posts?" + urllib.parse.urlencode(
            {"per_page": 100, "page": page, "status": "publish,future,draft", "_fields": "slug,title,status,link"})
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=hdr), timeout=45) as r:
                batch = json.loads(r.read().decode() or "[]")
        except urllib.error.HTTPError as e:
            if e.code == 400:
                break
            print(f"[warn] live WP check unavailable ({e}). Index-only.")
            return None
        except urllib.error.URLError as e:
            print(f"[warn] live WP check unavailable ({e}). Index-only.")
            return None
        if not batch:
            break
        for p in batch:
            rows.append((p.get("slug", ""), p.get("title", {}).get("rendered", ""), p.get("status"), p.get("link")))
        page += 1
    return rows


def main():
    args = sys.argv[1:]
    slug = None
    if "--slug" in args:
        i = args.index("--slug")
        slug = args[i + 1]
        extras = args[:i] + args[i + 2:]
    else:
        extras = args
        if extras:
            slug = extras[0].replace(" ", "-")
    if not slug:
        sys.exit("Pass --slug <target-slug>.")

    target = toks(slug)
    for e in extras:
        target |= toks(e)
    if not target:
        print("[warn] slug reduced to only generic words; relying on exact-slug check only.")
    dup = False

    # (a) index grep for the exact slug
    if os.path.exists(INDEX):
        idx = open(INDEX, encoding="utf-8").read().lower()
        if slug.lower() in idx:
            print(f"[index] exact slug already in index: {slug}")
            dup = True

    # (b) live WP: compare distinctive slug tokens
    env = load_env()
    posts = all_posts(env)
    if posts is not None:
        for eslug, etitle, estatus, elink in posts:
            et = toks(eslug)
            if slug.lower() == eslug.lower():
                print(f"[wp] EXACT slug exists: {estatus} {eslug} -> {elink}")
                dup = True
                continue
            if not target or not et:
                continue
            inter = target & et
            j = len(inter) / len(target | et)
            # real collision: same distinctive topic (target fully covered) or very high overlap
            if target.issubset(et) or j >= 0.7:
                print(f"[wp] topic collision ({int(j*100)}% token overlap) with {eslug} -> {elink}")
                dup = True
            elif len(inter) >= 2 and len(inter) == len(target):
                print(f"[wp] possible overlap with {eslug} (shared: {sorted(inter)})")
                dup = True

    if dup:
        print("\nRESULT: possible duplicate. Update in place, do not create new.")
        sys.exit(1)
    print("\nRESULT: clear to write.")
    sys.exit(0)


if __name__ == "__main__":
    main()
