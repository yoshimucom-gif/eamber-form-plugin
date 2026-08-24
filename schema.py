# -*- coding: utf-8 -*-
"""schema.py — 不動産のスキーマを電気工事のスキーマに差し替える。

2026-08-24。フォークしたプラグインの中で、業種に依存するのはここだけ。
仕組み（フォーム生成・検証・メール・DB・CSV・管理画面）は触らない。

対応の考え方:
  物件種別（マンション／戸建て／土地）で項目が変わる
    ↓ 同じ形
  工事内容（エアコン／分電盤・漏電／インターホン…）で聞くことが変わる

必須は4つだけ。FAQに会社自身が書いている
「症状と、お住まいの市町村。この2つがあれば話が始められます」に合わせる。
"""
import io
import os
import re
import sys

sys.stdout.reconfigure(encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))
P = os.path.join(HERE, "eamber-form", "eamber-form.php")

NEW_SCHEMA = '''/** お客様のご連絡先 */
function eaf_customer_fields() {
    return array(
        array('key'=>'name',         'label'=>'お名前',              'type'=>'text',   'col'=>'name',          'len'=>100, 'def'=>'req', 'ph'=>'例：山田 太郎'),
        array('key'=>'kana',         'label'=>'フリガナ',            'type'=>'text',   'col'=>'kana',          'len'=>100, 'def'=>'off', 'ph'=>'例：ヤマダ タロウ'),
        array('key'=>'tel',          'label'=>'電話番号',            'type'=>'tel',    'col'=>'tel',           'len'=>50,  'def'=>'req', 'ph'=>'例：090-1234-5678'),
        array('key'=>'contact_time', 'label'=>'ご連絡しやすい時間帯', 'type'=>'select', 'col'=>'contact_time',  'len'=>50,  'def'=>'opt', 'opts'=>'contact_time'),
        array('key'=>'contact_way',  'label'=>'ご希望の連絡方法',     'type'=>'select', 'col'=>null,            'len'=>50,  'def'=>'opt', 'opts'=>'contact_way'),
    );
}

/** ご状況（担当者が優先順位を付けるための情報） */
function eaf_situation_fields() {
    return array(
        array('key'=>'city',       'label'=>'お住まい・現場の市町村', 'type'=>'select', 'col'=>'city',      'len'=>50,  'def'=>'req', 'opts'=>'city'),
        array('key'=>'building',   'label'=>'建物の種類',            'type'=>'select', 'col'=>'building',  'len'=>50,  'def'=>'req', 'opts'=>'building'),
        array('key'=>'ownership',  'label'=>'持ち家か賃貸か',        'type'=>'select', 'col'=>'ownership', 'len'=>50,  'def'=>'opt', 'opts'=>'ownership'),
        array('key'=>'since',      'label'=>'いつからの症状ですか',   'type'=>'select', 'col'=>null,        'len'=>50,  'def'=>'opt', 'opts'=>'since'),
        array('key'=>'timing',     'label'=>'ご希望の時期',          'type'=>'select', 'col'=>'timing',    'len'=>50,  'def'=>'opt', 'opts'=>'timing'),
        array('key'=>'detail',     'label'=>'症状・ご希望',          'type'=>'textarea','col'=>'detail',   'len'=>2000,'def'=>'opt', 'ph'=>'例：2階の部屋のブレーカーだけ、エアコンを付けると落ちます'),
    );
}

/** 工事内容ごとの入力項目（内容によって聞くことが変わる） */
function eaf_property_fields() {
    return array(
        'aircon' => array(
            array('key'=>'ac_work',    'label'=>'ご希望の作業',        'type'=>'select', 'def'=>'req', 'opts'=>'ac_work'),
            array('key'=>'ac_floor',   'label'=>'設置する階',          'type'=>'select', 'def'=>'opt', 'opts'=>'ac_floor'),
            array('key'=>'ac_outlet',  'label'=>'専用コンセントの有無', 'type'=>'select', 'def'=>'opt', 'opts'=>'yesno_unknown'),
            array('key'=>'ac_body',    'label'=>'本体のご用意',        'type'=>'select', 'def'=>'opt', 'opts'=>'body_ready'),
            array('key'=>'ac_model',   'label'=>'型番（分かれば）',     'type'=>'text',   'def'=>'opt', 'ph'=>'例：AN-C223SE'),
        ),
        'breaker' => array(
            array('key'=>'br_symptom', 'label'=>'症状',                'type'=>'select', 'def'=>'req', 'opts'=>'br_symptom'),
            array('key'=>'br_scope',   'label'=>'止まっている範囲',     'type'=>'select', 'def'=>'opt', 'opts'=>'br_scope'),
            array('key'=>'br_smell',   'label'=>'焦げたにおいの有無',   'type'=>'select', 'def'=>'opt', 'opts'=>'yesno_unknown'),
            array('key'=>'br_wire',    'label'=>'単相2線式／3線式',     'type'=>'select', 'def'=>'off', 'opts'=>'wire_type'),
            array('key'=>'br_age',     'label'=>'分電盤の設置年（西暦）','type'=>'number','def'=>'opt', 'ph'=>'例：1995'),
        ),
        'intercom' => array(
            array('key'=>'ic_symptom', 'label'=>'症状',                'type'=>'select', 'def'=>'req', 'opts'=>'ic_symptom'),
            array('key'=>'ic_type',    'label'=>'いまの機種',          'type'=>'select', 'def'=>'opt', 'opts'=>'ic_type'),
            array('key'=>'ic_want',    'label'=>'ご希望の機種',        'type'=>'select', 'def'=>'opt', 'opts'=>'ic_want'),
            array('key'=>'ic_year',    'label'=>'建物の築年（西暦）',   'type'=>'number', 'def'=>'opt', 'ph'=>'例：2005'),
        ),
        'outlet' => array(
            array('key'=>'ol_work',    'label'=>'ご希望の作業',        'type'=>'select', 'def'=>'req', 'opts'=>'ol_work'),
            array('key'=>'ol_count',   'label'=>'箇所数',              'type'=>'number', 'def'=>'opt', 'ph'=>'例：2'),
            array('key'=>'ol_place',   'label'=>'設置場所',            'type'=>'select', 'def'=>'opt', 'opts'=>'ol_place'),
            array('key'=>'ol_volt',    'label'=>'100Vか200Vか',        'type'=>'select', 'def'=>'opt', 'opts'=>'volt'),
        ),
        'light' => array(
            array('key'=>'lt_work',    'label'=>'ご希望の作業',        'type'=>'select', 'def'=>'req', 'opts'=>'lt_work'),
            array('key'=>'lt_count',   'label'=>'台数',                'type'=>'number', 'def'=>'opt', 'ph'=>'例：4'),
            array('key'=>'lt_ceiling', 'label'=>'引掛シーリングの有無', 'type'=>'select', 'def'=>'opt', 'opts'=>'yesno_unknown'),
            array('key'=>'lt_height',  'label'=>'天井が高い場所か',     'type'=>'select', 'def'=>'opt', 'opts'=>'yesno_unknown'),
        ),
        'fan' => array(
            array('key'=>'fn_place',   'label'=>'設置場所',            'type'=>'select', 'def'=>'req', 'opts'=>'fn_place'),
            array('key'=>'fn_symptom', 'label'=>'症状',                'type'=>'select', 'def'=>'opt', 'opts'=>'fn_symptom'),
            array('key'=>'fn_type',    'label'=>'種類',                'type'=>'select', 'def'=>'opt', 'opts'=>'fn_type'),
        ),
        'business' => array(
            array('key'=>'bz_kind',    'label'=>'建物の用途',          'type'=>'select', 'def'=>'req', 'opts'=>'bz_kind'),
            array('key'=>'bz_work',    'label'=>'ご検討の工事',        'type'=>'select', 'def'=>'opt', 'opts'=>'bz_work'),
            array('key'=>'bz_stop',    'label'=>'停電させられる時間帯', 'type'=>'select', 'def'=>'opt', 'opts'=>'bz_stop'),
            array('key'=>'bz_tenant',  'label'=>'テナント入居か自社物件か','type'=>'select','def'=>'opt','opts'=>'bz_tenant'),
        ),
        'other' => array(
            array('key'=>'ot_note',    'label'=>'お困りの内容',        'type'=>'textarea','def'=>'opt', 'ph'=>'例：何が起きているか分かりませんが、時々部屋の電気が消えます'),
        ),
    );
}
'''

s = io.open(P, encoding="utf-8").read()

i = s.index("/** お客様のご連絡先 */")
j = s.index("/**\n * ティザー（記事内などに置く短い入口フォーム）に出せる項目。")
old = s[i:j]
s = s[:i] + NEW_SCHEMA + "\n" + s[j:]

io.open(P, "w", encoding="utf-8").write(s)
print(f"スキーマを差し替えた（{len(old):,} → {len(NEW_SCHEMA):,}バイト）")

# 工事内容の対応表
s = io.open(P, encoding="utf-8").read()
OLD_LABEL = re.search(r"\$GLOBALS\['EAF_PTYPE_LABEL'\][^;]+;", s)
NEW_LABEL = """$GLOBALS['EAF_PTYPE_LABEL'] = array(
    'aircon'   => 'エアコン（取り付け・修理）',
    'breaker'  => 'ブレーカーが落ちる・漏電・分電盤',
    'intercom' => 'インターホン',
    'outlet'   => 'コンセント・スイッチ',
    'light'    => '照明・LED化',
    'fan'      => '換気扇',
    'business' => '店舗・事務所・工場の設備',
    'other'    => 'その他・分からない',
);"""
if OLD_LABEL:
    s = s[:OLD_LABEL.start()] + NEW_LABEL + s[OLD_LABEL.end():]
    io.open(P, "w", encoding="utf-8").write(s)
    print("工事内容の対応表を差し替えた（8種）")
else:
    print("★ EAF_PTYPE_LABEL が見つからない")
