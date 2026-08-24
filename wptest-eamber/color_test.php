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
t('6桁を保存', eaf_opt('color_brand', '#1f6feb'), '#e91e63');
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#abc')));
t('3桁を保存', eaf_opt('color_brand', '#1f6feb'), '#abc');
t('3桁でもrgbに変換できる', eaf_hex_to_rgb('#abc'), '170,187,204');
foreach (array('あか', '', '<script>alert(1)</script>') as $bad) {
    update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => $bad)));
    t('不正な値は初期値に: ' . ($bad === '' ? '(空欄)' : mb_substr($bad, 0, 10)), eaf_opt('color_brand', '#1f6feb'), '#1f6feb');
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
$contrast = round((1.05) / ($lum('#e65100') + 0.05), 2);
t('既定色に白文字のコントラストが3.5以上', $contrast >= 3.5, true);
printf("     （実測 %.2f : 1）
", $contrast);
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#1f2e43', 'color_btn_bg' => 'あか')));
t('不正値も保存されない', eaf_opt('color_btn_bg', 'EMPTY'), 'EMPTY');
update_option(EAF_OPT, eaf_sanitize_options(array('color_brand' => '#1f2e43', 'color_btn_bg' => '#ff8a00')));
t('指定すれば保存される', eaf_opt('color_btn_bg', 'EMPTY'), '#ff8a00');

/* --- 3. 設定画面の入力欄 --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'color_brand' => '#1f2e43', 'color_btn_bg' => '#ff8a00', 'color_badge' => '#00a86b',
)));
$field = eaf_color_field('color_badge', '#ff5a36');
t('テキスト欄に現在値が入る', strpos($field, 'value="#00a86b"') !== false, true);
t('初期値ボタンに既定色',     strpos($field, 'data-default="#ff5a36"') !== false, true);

/* --- 4. CSSへの反映（★ここが最初の eaf_shortcode 呼び出し） --- */
$css = eaf_shortcode(array());
t('CSSが出力されている',           strpos($css, '<style>') !== false, true);
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
