#!/usr/bin/env python3
"""Read the gsutil signurl TSV and emit a portable bash script that downloads
every object into storage/app/public/<path> (stripping the 'public/' prefix).

Usage:
    python build_download_script.py signed_urls.tsv download_media.sh
"""
import sys

BUCKET_PREFIX = "gs://forsa_staging/public/"


def main():
    tsv, out = sys.argv[1], sys.argv[2]
    raw = open(tsv, "rb").read()
    if raw[:2] in (b"\xff\xfe", b"\xfe\xff"):
        text = raw.decode("utf-16")
    else:
        text = raw.decode("utf-8", errors="replace")
    lines = text.splitlines()
    rows = []
    for ln in lines:
        if not ln.strip():
            continue
        if ln.startswith("URL\t") or ln.startswith("gs://") is False:
            # header or wrapped line; only accept lines starting with gs://
            if not ln.startswith("gs://"):
                continue
        parts = ln.split("\t")
        if len(parts) < 4:
            continue
        gs_path = parts[0]
        signed = parts[3]
        if not gs_path.startswith(BUCKET_PREFIX):
            continue
        rel = gs_path[len(BUCKET_PREFIX):]
        rows.append((rel, signed))

    script = ["#!/bin/bash",
              "# Download Fursa media from GCS signed URLs into Laravel public storage.",
              "# Run from the Laravel project root (where 'artisan' lives).",
              "set -e",
              'BASE="storage/app/public"',
              'mkdir -p "$BASE"',
              'echo "Downloading %d files..."' % len(rows),
              "n=0"]
    for rel, signed in rows:
        d = rel.rsplit("/", 1)[0] if "/" in rel else ""
        if d:
            script.append('mkdir -p "$BASE/%s"' % d)
        script.append('n=$((n+1)); echo "[$n/%d] %s"' % (len(rows), rel))
        script.append('curl -fSL -o "$BASE/%s" "%s"' % (rel, signed))
    script.append('echo "DONE. Now run: php artisan storage:link"')
    script.append("")

    with open(out, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(script))
    print("files: %d" % len(rows))
    print("wrote: %s" % out)


if __name__ == "__main__":
    main()
