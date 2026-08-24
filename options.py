# -*- coding: utf-8 -*-
"""options.py — 選択肢のリストを電気工事向けに差し替える。

市町村は山梨県27市町村＋県外。会社概要の対応エリアに合わせる。
"""
import io
import os
import sys

sys.stdout.reconfigure(encoding="utf-8")
HERE = os.path.dirname(os.path.abspath(__file__))
P = os.path.join(HERE, "eamber-form", "eamber-form.php")

NEW = """function eaf_opt_list($key) {
    switch ($key) {
        /* ── 共通 ───────────────────────────────────── */
        case 'city': return array(
            '甲府市','富士吉田市','都留市','山梨市','大月市','韮崎市','南アルプス市',
            '北杜市','甲斐市','笛吹市','上野原市','甲州市','中央市',
            '市川三郷町','早川町','身延町','南部町','富士川町','昭和町',
            '道志村','西桂町','忍野村','山中湖村','鳴沢村','富士河口湖町',
            '小菅村','丹波山村','山梨県外');
        case 'building':     return array('戸建て','アパート・マンション','店舗・事務所','工場・倉庫','その他');
        case 'ownership':    return array('持ち家','賃貸','分からない');
        case 'since':        return array('今日','数日前から','1か月以上前から','これから工事したい');
        case 'timing':       return array('できるだけ早く','今週中','来週以降','未定・相談したい');
        case 'contact_time': return array('指定なし','午前（9〜12時）','午後（12〜17時）','夕方以降（17〜18時）','平日のみ希望','土日のみ希望');
        case 'contact_way':  return array('電話','メール','どちらでも');
        case 'yesno_unknown':return array('ある','ない','分からない');
        case 'volt':         return array('100V','200V','分からない');
        case 'wire_type':    return array('単相2線式','単相3線式','分からない');

        /* ── エアコン ───────────────────────────────── */
        case 'ac_work':   return array('新しく取り付けたい','入れ替えたい','移設したい','取り外したい','効かない・水漏れ（修理）');
        case 'ac_floor':  return array('1階','2階','3階以上','分からない');
        case 'body_ready':return array('自分で用意済み・購入予定','e.Amberに手配してほしい','未定');

        /* ── ブレーカー・漏電・分電盤 ───────────────── */
        case 'br_symptom':return array('落ちて戻らない','何度も落ちる','焦げたにおいがする','電気代が急に上がった','分電盤を交換したい','契約アンペアを上げたい');
        case 'br_scope':  return array('家全体','特定の部屋だけ','特定の機器だけ','分からない');

        /* ── インターホン ───────────────────────────── */
        case 'ic_symptom':return array('鳴らない','映らない','勝手に鳴る','古いので替えたい','新しく付けたい');
        case 'ic_type':   return array('音声のみ','カメラ付き（モニターあり）','チャイムのみ','分からない');
        case 'ic_want':   return array('同等品でよい','カメラ付きにしたい','スマホ連動にしたい','相談したい');

        /* ── コンセント・スイッチ ───────────────────── */
        case 'ol_work':   return array('増設したい','交換したい','焦げ臭い・熱い','スイッチの調子が悪い','屋外に付けたい','EV充電用を付けたい');
        case 'ol_place':  return array('居室','台所','洗面・浴室','屋外','駐車場','その他');

        /* ── 照明 ───────────────────────────────────── */
        case 'lt_work':   return array('交換したい','LED化したい','チカチカする・点かない','新しく付けたい');

        /* ── 換気扇 ─────────────────────────────────── */
        case 'fn_place':  return array('台所','浴室','トイレ','洗面所','その他');
        case 'fn_symptom':return array('動かない','異音がする','風が弱い','古いので替えたい');
        case 'fn_type':   return array('プロペラ式','シロッコ（レンジフード）','天井埋込型','分からない');

        /* ── 法人 ───────────────────────────────────── */
        case 'bz_kind':   return array('店舗','事務所','工場','倉庫','アパート・マンション（オーナー）','その他');
        case 'bz_work':   return array('業務用エアコン','LED化','キュービクル更新','LAN配線','新築','改装・原状回復','その他');
        case 'bz_stop':   return array('営業時間中でも可','営業時間外のみ','休業日のみ','止められない','相談したい');
        case 'bz_tenant': return array('テナントとして入居','自社物件','オーナーとして所有','分からない');
    }
    return array();
}"""

s = io.open(P, encoding="utf-8").read()
i = s.index("function eaf_opt_list($key) {")
j = s.index("\n\n/* =====", i)
old = s[i:j]
s = s[:i] + NEW + s[j:]
io.open(P, "w", encoding="utf-8").write(s)
print(f"選択肢を差し替えた（{len(old):,} → {len(NEW):,}バイト）")

# 旧不動産の選択肢キーが残っていないか
import re
left = set(re.findall(r"'(direction|land_right|structure|road_\w+|layout|"
                      r"current_use|survey|purpose|relation|loan|other_agent)'", s))
print("旧キーの残り:", left or "0件")
