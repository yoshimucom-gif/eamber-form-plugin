<?php
/**
 * フォームの横幅。
 *
 * 本文幅の広いテーマだとタイルが1枚ずつ巨大になり、一覧として見渡せなくなる。
 * ショートコードの width と、設定「フォームの横幅」の両方で絞れること、
 * そして壊れた値を style に流し込まないことを確かめる。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/w_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }
/** ラッパに出た max-width を取り出す */
function maxw($atts = array()) {
    $h = eaf_shortcode($atts);
    return preg_match('/class="fhs-wrap[^"]*"[^>]*style="max-width:([^"]+)"/', $h, $m) ? $m[1] : '';
}

/* CSSはキューに積まれるので、1回でも描画すれば取り出せる */
$FIRST = eaf_shortcode(array());

/* --- 1. 値の正規化 --- */
t('数字だけならpxを補う', eaf_parse_width('680'), '680px');
t('単位つきはそのまま',   eaf_parse_width('42rem'), '42rem');
t('パーセントも通す',     eaf_parse_width('100%'), '100%');
t('空欄は空欄',           eaf_parse_width('  '), '');
/* ★style属性に直接入る値なので、通す書き方を絞る（ホワイトリスト）。
   ここが緩いと、ショートコードから任意のCSSを差し込まれる。 */
foreach (array('abc', '680px;background:red', '680px !important', 'expression(1)', '-100px', '100vw;x') as $bad) {
    t('通さない: ' . $bad, eaf_parse_width($bad), '');
}

/* --- 2. 既定の幅 --- */
set_opts(array());
t('標準は680px',          maxw(array()), '680px');
t('カードも680px',        maxw(array('design' => 'card')), '680px');
t('コンパクトは440px',    maxw(array('design' => 'compact')), '440px');
t('ティザー横長は100%',   maxw(array('design' => 'teaser', 'url' => '/contact/')), '100%');
t('ティザー縦は440px',    maxw(array('design' => 'teaser-v', 'url' => '/contact/')), '440px');

/* --- 3. 設定で絞れる --- */
set_opts(array('form_width' => '900'));
t('設定が標準に効く',     maxw(array()), '900px');
t('設定はティザーには効かせない', maxw(array('design' => 'teaser', 'url' => '/contact/')), '100%');
set_opts(array('form_width' => '48rem'));
t('remでも保存して効く',  maxw(array()), '48rem');

/* --- 4. ショートコードが設定より優先 --- */
set_opts(array('form_width' => '900'));
t('width属性が勝つ',      maxw(array('width' => '720')), '720px');
t('カードにも効く',       maxw(array('design' => 'card', 'width' => '600')), '600px');
t('コンパクトにも効く',   maxw(array('design' => 'compact', 'width' => '380')), '380px');
t('ティザーにも効く',     maxw(array('design' => 'teaser', 'url' => '/contact/', 'width' => '820')), '820px');
t('壊れた指定は既定に戻る', maxw(array('width' => '720px;background:red')), '900px');

/* --- 5. 中央寄せとタイルの列数（CSSはキューから取り出す） --- */
$css = fake_inline_style();
t('中央に寄せている', strpos($css, 'margin:0 auto') !== false, true);
/* 幅を絞ってもタイルは3列のまま（1列に崩れると縦に伸びきる） */
t('タイルは3列を保つ', strpos($css, '.fhs-tiles{display:grid;grid-template-columns:repeat(3,1fr)') !== false, true);

/* --- 6. 狭い画面での左右の余白 ---
   ★導入先のページが「全幅（alignfull）」ブロックだと、テーマ側の左右余白が
     ゼロになり、フォームが画面の端にぴったり張り付く。実際に eamber.jp の
     お問い合わせページがこの状態だった（先祖の要素すべて padding 0）。
     テーマに頼らず、フォーム自身が余白を持つ。 */
t('狭い画面用の余白の指定がある', strpos($css, '@media(max-width:720px)') !== false, true);
t('カードに左の余白を付ける',     strpos($css, '.fhs-wrap .fhs-card{padding-left:16px') !== false, true);
t('右の余白も付ける',             strpos($css, 'padding-right:16px}') !== false, true);
/* 広い画面では中央に寄って余白が生まれるので、常時は効かせない */
t('常時の余白は増やさない',       strpos($css, '.fhs-card{background:transparent;border:0;border-radius:0;padding:0 0 28px}') !== false, true);

/* --- 7. 自己診断 --- */
t('自己診断: max-widthを取り出せている', maxw(array('width' => '555')), '555px');

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
