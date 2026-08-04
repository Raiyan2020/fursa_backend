#!/usr/bin/env python3
"""Find duplicate emails inside the users INSERT block of a MySQL dump."""
import re
import sys
from collections import Counter

path = sys.argv[1] if len(sys.argv) > 1 else "fursa_prod_mysql.sql"
text = open(path, encoding="utf-8", errors="replace").read()

m = re.search(
    r"INSERT INTO `users` .*? VALUES\n(.*?)(?=\n\nTRUNCATE|\n\nINSERT INTO|\Z)",
    text,
    re.S,
)
if not m:
    print("No users INSERT block found")
    sys.exit(1)

block = m.group(1)
emails = [e.lower() for e in re.findall(r"'([^']*@[^']*)'", block)]
counts = Counter(emails)
dups = [(e, n) for e, n in counts.items() if n > 1]

print(f"email tokens: {len(emails)}")
print(f"unique: {len(counts)}")
print(f"duplicates: {len(dups)}")
for e, n in sorted(dups, key=lambda x: -x[1])[:50]:
    print(f"{n}\t{e}")
