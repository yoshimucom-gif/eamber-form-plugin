# -*- coding: utf-8 -*-
"""rename.py — 本気査定プラグインをフォークして e.Amber 用の識別子に置き換える。

2026-08-24 吉村さん判断。案A（別プラグインとしてフォーク）。

  fhs_ / FHS_        → eaf_ / EAF_
  fudosan-honki      → eamber-form
  DBテーブル・オプション名も変える（同居しても衝突しないように）

★1回だけ実行する。2回目は置き換え済みなので何も起きない。
"""
import io
import os
import re
import sys

sys.stdout.reconfigure(encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))

RULES = [
    # 接頭辞。長いものから順に
    (r"\bFHS_", "EAF_"),
    (r"\bfhs_", "eaf_"),
    (r"fudosan-honki", "eamber-form"),
    (r"fudosan_honki", "eamber_form"),
]

# プラグインヘッダーと配信先は個別に差し替える
HEADER = [
    ("Plugin Name: 不動産 訪問査定申込（本気査定）",
     "Plugin Name: e.Amber お問い合わせフォーム"),
    ("不動産 訪問査定申込（本気査定）", "e.Amber お問い合わせフォーム"),
    ("ミカタ株式会社", "株式会社Keys"),
    ("yoshimucom-gif/fudosan-honki-plugin", "yoshimucom-gif/eamber-form-plugin"),
]

targets = []
for root, _, files in os.walk(HERE):
    for f in files:
        if f.endswith((".php", ".txt", ".json", ".py", ".md")):
            targets.append(os.path.join(root, f))

total = 0
for p in targets:
    if os.path.basename(p) == "rename.py":
        continue
    s = io.open(p, encoding="utf-8").read()
    orig = s
    n = 0
    for rx, rep in RULES:
        s, k = re.subn(rx, rep, s)
        n += k
    for a, b in HEADER:
        if a in s:
            s = s.replace(a, b)
            n += 1
    if s != orig:
        io.open(p, "w", encoding="utf-8").write(s)
        total += n
        print(f"  {os.path.relpath(p, HERE)}  {n}箇所")

print(f"\n合計 {total}箇所")

# ── 残っていないか ────────────────────────────────────
left = []
for p in targets:
    if os.path.basename(p) == "rename.py":
        continue
    s = io.open(p, encoding="utf-8").read()
    for m in re.finditer(r"fhs_|FHS_|fudosan.honki|ミカタ", s):
        left.append((os.path.relpath(p, HERE), m.group()))
print("\n残り: " + (str(len(left)) + "件 " + str(set(x[1] for x in left))
                   if left else "0件"))
