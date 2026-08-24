# -*- coding: utf-8 -*-
"""cleanup.py — スキーマ差し替え後に残った不動産固有の参照を直す。

ティザー項目・メールの差し込み・CSVの列・既定の文言。
ここが合っていないとフォームや通知が壊れる。
"""
import io
import os
import re
import sys

sys.stdout.reconfigure(encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))
P = os.path.join(HERE, "eamber-form", "eamber-form.php")

s = io.open(P, encoding="utf-8").read()
n = 0

# ── 1. ティザー項目 ────────────────────────────────────
OLD_T = """        'ptype'   => array('label' => '物件種別',          'type' => 'ptype'),
        'address' => array('label' => '物件の住所',        'type' => 'text',   'ph' => ''),   // 入力例は eaf_address_placeholder()
        'survey'  => array('label' => 'ご希望の査定方法',   'type' => 'select', 'opts' => 'survey'),
        'purpose' => array('label' => 'ご事情・売却の理由', 'type' => 'select', 'opts' => 'purpose'),
        'timing'  => array('label' => '売却をご希望の時期', 'type' => 'select', 'opts' => 'timing'),"""
NEW_T = """        'ptype'   => array('label' => 'お困りの内容',   'type' => 'ptype'),
        'city'    => array('label' => '市町村',        'type' => 'select', 'opts' => 'city'),
        'timing'  => array('label' => 'ご希望の時期',   'type' => 'select', 'opts' => 'timing'),"""
if OLD_T in s:
    s = s.replace(OLD_T, NEW_T, 1)
    n += 1
    print("  ティザー項目を差し替え（物件種別・住所・査定方法 → 工事内容・市町村・時期）")

# ── 2. メールの差し込み ────────────────────────────────
FIX = [
    ("'{survey}'            => isset($ctx['survey']) ? $ctx['survey'] : '',",
     "'{city}'              => isset($ctx['city']) ? $ctx['city'] : '',"),
    ("'survey'  => isset($situ['survey']) ? $situ['survey']['val'] : '',",
     "'city'    => isset($situ['city']) ? $situ['city']['val'] : '',"),
    ("'survey' => '訪問査定（実際に見てもらいたい）',",
     "'city' => '甲府市',"),
    ("$site = eaf_opt('site_name', '不動産査定');",
     "$site = eaf_opt('site_name', '株式会社e.Amber');"),
    ("'ptype_label' => $label,", "'ptype_label' => $label,"),
]
for a, b in FIX:
    if a in s and a != b:
        s = s.replace(a, b, 1)
        n += 1

# ── 3. CSVの列 ─────────────────────────────────────────
OLD_C = ("$cols = array('id','created_at','name','kana','tel','email','contact_time',"
         "'owner_address','ptype','address','survey','purpose','timing','details',"
         "'marketing_opt_in');")
NEW_C = ("$cols = array('id','created_at','name','kana','tel','email','contact_time',"
         "'ptype','city','building','ownership','timing','detail','details',"
         "'marketing_opt_in');")
if OLD_C in s:
    s = s.replace(OLD_C, NEW_C, 1)
    n += 1
    print("  CSVの列を差し替え")

# ── 4. 住所欄まわり（この案件では市町村の選択に置き換わった）──
s = s.replace("'物件の住所'", "'現場の市町村'")
s = s.replace("物件住所", "現場の市町村")

io.open(P, "w", encoding="utf-8").write(s)
print(f"\n{n}箇所を修正")

# ── 残っている不動産の語 ───────────────────────────────
left = {}
for w in ("survey", "purpose", "owner_address", "物件", "査定", "売却", "マンション",
          "戸建", "土地", "不動産", "宅建"):
    c = len(re.findall(w, s))
    if c:
        left[w] = c
print("\n残っている不動産の語:")
for w, c in sorted(left.items(), key=lambda x: -x[1]):
    print(f"  {w:12s} {c}箇所")
