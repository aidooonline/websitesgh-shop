#!/usr/bin/env python3
"""
Publish the back-dated batch from config/schedule.json.
Only entries whose article file exists in articles/<slug>.html are published.
Entries marked to_write (no article yet) are skipped and reported, so you can
write them, then re-run (idempotent: publish.py updates by slug, never duplicates).

Run:  python publish_batch.py            (publish all ready back-dated entries)
      python publish_batch.py --future   (publish all ready FUTURE entries, status=future)
      python publish_batch.py --dry-run  (show what would publish)
      python publish_batch.py --slug X    (publish one entry only)

Future entries are auto-scheduled by WordPress to their slot date (status=future).
Idempotent: publish.py updates by slug, never duplicates.
"""
import json, os, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE)

def main():
    dry = "--dry-run" in sys.argv
    future = "--future" in sys.argv
    only = None
    if "--slug" in sys.argv:
        only = sys.argv[sys.argv.index("--slug") + 1]
    sched = json.load(open(os.path.join(ROOT, "config", "schedule.json")))
    key = "future" if future else "backdated"
    status = "future" if future else "publish"
    published, skipped = [], []
    for e in sched[key]:
        slug = e["slug"]
        if only and slug != only:
            continue
        art = os.path.join(ROOT, "articles", f"{slug}.html")
        if not os.path.exists(art):
            skipped.append((e["slot"], slug, "no article file (to_write)"))
            continue
        cmd = [sys.executable, os.path.join(HERE, "publish.py"),
               "--slug", slug, "--title", e["title"], "--html", art,
               "--date", e["date"], "--status", status,
               "--category", e.get("category", ""), "--tags", e.get("tags", "")]
        if dry:
            cmd.append("--dry-run")
        print(f"\n=== slot {e['slot']}  {slug}  ({e['date'][:10]}) ===")
        r = subprocess.run(cmd)
        if r.returncode == 0:
            published.append(slug)
        else:
            skipped.append((e["slot"], slug, f"publish.py exit {r.returncode}"))

    print("\n================ SUMMARY ================")
    print(f"published/ok: {len(published)}")
    for s in published:
        print(f"  + {s}")
    print(f"skipped: {len(skipped)}")
    for slot, slug, why in skipped:
        print(f"  - slot {slot} {slug}: {why}")
    if any("to_write" in w for _, _, w in skipped):
        print("\nNext: write the to_write articles into articles/<slug>.html, then re-run.")

if __name__ == "__main__":
    main()
