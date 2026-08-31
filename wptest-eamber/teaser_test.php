<?php
/**
 * ティザーの並び。
 *
 * ★工事内容のタイルは3行ぶんの高さ、市町村は1行しかない。
 *   素直に横へ並べると右側が大きく空いて、間の抜けた形になる（実際そうなっていた）。
 *   市町村とボタンを右の列にまとめ、ボタンを列の下端に落として高さを揃える。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/tz_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function has($h, $s) { return strpos($h, $s) !== false; }
/** 開始タグから対応する範囲をざっくり切り出す（次の兄弟divの直前まで） */
function seg($html, $start, $end) {
    $i = strpos($html, $start); if ($i === false) return '';
    $j = strpos($html, $end, $i);
    return $j === false ? substr($html, $i) : substr($html, $i, $j - $i);
}

$teaser = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/'));
$css    = fake_inline_style();

/* --- 1. 2列に分かれる --- */
t('分割用の印が付く',      has($teaser, 'class="fhs-trow fhs-trow-split"'), true);
t('左の列がある',          has($teaser, 'class="fhs-tcol-main"'), true);
t('右の列がある',          has($teaser, 'class="fhs-tcol-side"'), true);

$main = seg($teaser, '<div class="fhs-tcol-main">', '<div class="fhs-tcol-side">');
$side = seg($teaser, '<div class="fhs-tcol-side">', '</form>');
t('左はタイルだけ',        has($main, 'fhs-tile-input') && !has($main, 'name="address"'), true);
/* ★既定は工事内容だけ。ティザーの仕事は押してもらうことなので、
     市町村を選ばせても先の手間が1つ減るだけで、入口で作業を増やすほうが損。 */
t('既定では市町村を出さない', has($side, 'name="address"'), false);
t('右にはボタンがある',        has($side, 'fhs-submit'), true);
t('右にボタンがある',      has($side, 'fhs-submit'), true);
/* 市町村を出したいときは fields で足せる。その順序も見る */
$t2   = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/', 'fields' => 'ptype,address'));
$side2 = seg($t2, '<div class="fhs-tcol-side">', '</form>');
t('指定すれば市町村を出せる', has($side2, 'name="address"'), true);
t('ボタンは市町村より後ろ',   strpos($side2, 'name="address"') < strpos($side2, 'fhs-submit'), true);
/* ★ラベルは本フォームと同じ言い方にそろえる（「市町村」だけだと何の話か伝わらない） */
t('市町村のラベルは本フォームと同じ', has($side2, 'お住まい・現場の市町村'), true);
t('「市町村」だけの見出しは使わない', preg_match('/>市町村</', $side2) === 1, false);
/* ★注記はボタンに掛かる文。カード全幅に流すと左のタイルの下に取り残される */
t('注記も右の列にある',    has($side, 'fhs-tnote'), true);
t('注記はボタンより後ろ',  strpos($side, 'fhs-submit') < strpos($side, 'fhs-tnote'), true);
t('注記はタイル側に無い',  has($main, 'fhs-tnote'), false);

/* --- 2. 高さを揃える仕掛けがCSSに入っている --- */
t('右列を下まで伸ばす',    has($css, '.fhs-design-teaser .fhs-trow-split{align-items:stretch}'), true);
t('ボタンを下端へ落とす',  has($css, '.fhs-tcol-side .fhs-tcta{flex:0 0 auto;margin-top:auto'), true);
t('右列のボタンは列幅いっぱい', has($css, '.fhs-tcol-side .fhs-tcta button{max-width:none'), true);

/* --- 3. STEPの番号 ---
   ★既定では出さない。本フォームの「STEP 1 / 2」と字面が同じで、
     ティザーの STEP 2 を終えて進むと本フォームの STEP 1 に戻るため、
     進んだのか戻ったのか分からなくなる。 */
t('既定ではSTEP番号を出さない', has($teaser, 'class="fhs-step"'), false);
$t3 = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/', 'fields' => 'ptype,address', 'steps' => '1'));
t('出したいときだけ出せる',     has($t3, 'class="fhs-step"'), true);
t('そのときは通しの番号',
  strpos($t3, 'STEP 1') < strpos($t3, 'STEP 2'), true);

/* --- 4. 工事内容を出さない指定なら、分割しない --- */
$noTile = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/', 'fields' => 'address,timing'));
t('タイルが無ければ分割しない', has($noTile, 'fhs-trow-split'), false);
t('その場合も項目は出る',       has($noTile, 'name="address"') && has($noTile, 'name="situation_timing"'), true);
t('その場合もボタンは出る',     has($noTile, 'fhs-submit'), true);
t('その場合も注記は出る',       has($noTile, 'fhs-tnote'), true);

/* --- 5. 本フォームには列分割を持ち込まない --- */
$main_form = eaf_shortcode(array());
t('本フォームは分割しない', has($main_form, 'fhs-tcol-main'), false);

/* --- 6. 自己診断 --- */
t('自己診断: ティザーを取り出せている', has($teaser, 'fhs-design-teaser'), true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
