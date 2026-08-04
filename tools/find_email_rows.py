#!/usr/bin/env python3
import re
import sys

path = sys.argv[1] if len(sys.argv) > 1 else "fursa_prod_mysql.sql"
needle = (sys.argv[2] if len(sys.argv) > 2 else "aahmadalk16@gmail.com").lower()
text = open(path, encoding="utf-8").read()
for line in text.splitlines():
    if needle in line.lower() and line.strip().startswith("("):
        m = re.match(r"\('(\d+)'", line.strip())
        uid = m.group(1) if m else "?"
        # extract email token roughly
        emails = re.findall(r"'([^']*@[^']*)'", line)
        print(f"id={uid} emails={emails}")
