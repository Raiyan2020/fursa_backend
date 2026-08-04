#!/usr/bin/env python3
"""Find case-insensitive duplicates for username/email in users INSERT."""
import re
import sys
from collections import defaultdict

path = sys.argv[1] if len(sys.argv) > 1 else "fursa_prod_mysql.sql"
text = open(path, encoding="utf-8").read()
m = re.search(
    r"INSERT INTO `users` \((.*?)\) VALUES\n(.*?)(?=\n\nTRUNCATE|\n\nINSERT INTO|\Z)",
    text,
    re.S,
)
if not m:
    raise SystemExit("users block not found")

cols = [c.strip().strip("`") for c in m.group(1).split(",")]
block = m.group(2)

# parse rows roughly with csv-like SQL values
rows = []
for line in block.splitlines():
    line = line.strip().rstrip(",")
    if not line.startswith("("):
        continue
    # strip surrounding () and trailing );
    inner = line[1:]
    if inner.endswith(");"):
        inner = inner[:-2]
    elif inner.endswith(")"):
        inner = inner[:-1]
    # split by comma respecting quotes
    vals = []
    cur = []
    in_q = False
    i = 0
    while i < len(inner):
        ch = inner[i]
        if ch == "'" and not in_q:
            in_q = True
            cur.append(ch)
        elif ch == "'" and in_q:
            # escaped '' ?
            if i + 1 < len(inner) and inner[i + 1] == "'":
                cur.append("''")
                i += 1
            else:
                in_q = False
                cur.append(ch)
        elif ch == "," and not in_q:
            vals.append("".join(cur).strip())
            cur = []
        else:
            cur.append(ch)
        i += 1
    if cur:
        vals.append("".join(cur).strip())
    if len(vals) != len(cols):
        continue
    row = dict(zip(cols, vals))
    rows.append(row)

print(f"parsed rows: {len(rows)}")

for field in ("username", "email", "manual_id", "phone_number", "civil_id"):
    if field not in cols:
        continue
    buckets = defaultdict(list)
    for r in rows:
        raw = r[field]
        if raw == "NULL":
            continue
        val = raw.strip("'")
        if val == "":
            continue
        buckets[val.lower()].append((r["id"].strip("'"), val))
    dups = {k: v for k, v in buckets.items() if len(v) > 1}
    print(f"\n=== {field}: {len(dups)} case-insensitive dup groups ===")
    for k, v in list(dups.items())[:20]:
        print(k, "->", v)
