<?php
/**
 * 色設定の保存と、フォームへの反映。
 *
 * ★CSS（<style>）はページに1回しか出力されない仕様なので、
 *   CSSを見る検査は「最初の eaf_shortcode 呼び出し」でまとめて行うこと。
 *   2回目以降の戻り値には <style> が入らない。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/color_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s\n     got=%s want=%s\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true), var_export($w, true));
}

/* --- 1. 保存まわり（CSSを見ない検査を先に済ませる） --- */
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#e91e63')));
t('6桁を保存', eaf_opt('color_brand', 'FALLBACK'), '#e91e63');
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#abc')));
t('3桁を保存', eaf_opt('color_brand', 'FALLBACK'), '#abc');
t('3桁でもrgbに変換できる', eaf_hex_to_rgb('#abc'), '170,187,204');
foreach (array('あか', '', '<script>alert(1)</script>') as $bad) {
    update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => $bad)));
    t('不正な値は既定に戻る: ' . ($bad === '' ? '(空欄)' : mb_substr($bad, 0, 10)), eaf_opt('color_brand', 'FALLBACK'), '#1E3050');
}

/* --- 2. ボタンの背景色は「空欄ならブランドカラー」 --- */
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#1f2e43', 'color_btn_bg' => '')));
t('空欄なら保存もされない', eaf_opt('color_btn_bg', 'EMPTY'), 'EMPTY');
// 白文字が読める濃さか（大きい太字なので3:1以上あればよい）
$lum = function ($hex) {
    $hex = ltrim($hex, '#');
    $ch = array();
    foreach (array(0, 2, 4) as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $ch[] = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
};
/* ★既定はアンバーのボタンに紺文字。白文字だと 2.4:1 しかなく沈むので、
     この組み合わせのコントラストを検査で固定しておく。 */
$ratio = function ($a, $b) use ($lum) {
    $x = $lum($a); $y = $lum($b);
    if ($x < $y) { $t = $x; $x = $y; $y = $t; }
    return round(($x + 0.05) / ($y + 0.05), 2);
};
$contrast = $ratio('#E8A33D', '#1E3050');
t('既定のボタン（アンバー地に紺文字）が4.5以上', $contrast >= 4.5, true);
$white = $ratio('#E8A33D', '#ffffff');
t('参考: 同じ地に白文字だと3未満', $white < 3.0, true);
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#1f2e43', 'color_btn_bg' => 'あか')));
t('不正値も保存されない', eaf_opt('color_btn_bg', 'EMPTY'), 'EMPTY');
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#1f2e43', 'color_btn_bg' => '#ff8a00')));
t('指定すれば保存される', eaf_opt('color_btn_bg', 'EMPTY'), '#ff8a00');

/* --- 3. 設定画面の入力欄 --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'color_brand' => '#1f2e43', 'color_btn_bg' => '#ff8a00', 'color_badge' => '#00a86b',
)));
$field = eaf_color_field('color_badge', '#E8A33D');
t('テキスト欄に現在値が入る', strpos($field, 'value="#00a86b"') !== false, true);
t('初期値ボタンに既定色',     strpos($field, 'data-default="#E8A33D"') !== false, true);

/* --- 4. CSSへの反映 ---
   ★CSSはショートコードの戻り値ではなく、WordPressのキューに積まれる。
     （本文以外でも the_content が回るため、出力をWPに任せる作りにした） */
eaf_shortcode(array());
$css = fake_inline_style();
/* キューに積むのでタグは含まない。CSS本体が入っていることを見る */
t('CSSが積まれている',             strpos($css, '.fhs-wrap{--fhs-brand:') !== false, true);
t('ブランドカラーが入る',           strpos($css, '--fhs-brand:#1f2e43') !== false, true);
t('ボタン背景を別色にできる',       strpos($css, '--fhs-btn-bg:#ff8a00') !== false, true);
t('バッジ色が入る',                 strpos($css, '--fhs-badge-bg:#00a86b') !== false, true);
t('ボタンが専用変数を使っている',   strpos($css, 'background:var(--fhs-btn-bg)') !== false, true);
t('「戻る」は白のまま',             strpos($css, '.fhs-wrap .fhs-back{flex:0 0 34%;background:#fff') !== false, true);

/* --- 5. 自己診断：CSSは2回目以降に出ない（この前提が崩れたら上の検査が無意味になる） --- */
$second = eaf_shortcode(array('design' => 'teaser', 'url' => '/satei/'));
t('自己診断: 2回目にCSSは出ない', strpos($second, '<style>') !== false, false);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
