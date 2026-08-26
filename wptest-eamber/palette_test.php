<?php
/**
 * タイルの配色パターン。
 *
 * ★CSSはページに1回しか出力されないので、3パターンを1プロセスで見比べられない。
 *   生成関数 eaf_tile_palette_css() を直接検査し、
 *   「最初の出力に入っていること」だけをフォーム側で確かめる。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/pal_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_pal($v) { update_option(EAF_OPT, eaf_sanitize_options(array('tile_palette' => $v))); }
function has($h, $s) { return strpos($h, $s) !== false; }

/* --- 1. 用意されている選択肢 --- */
$all = eaf_tile_palettes();
t('3パターンある', array_keys($all), array('brand', 'amber', 'colorful'));
foreach ($all as $k => $v) t('ラベルがある: ' . $k, isset($v['label']) && $v['label'] !== '', true);

/* --- 2. 既定はブランド（サイトになじむもの） --- */
update_option(EAF_OPT, eaf_sanitize_options(array()));
t('既定はブランド', eaf_opt('tile_palette', 'brand'), 'brand');
$css = eaf_tile_palette_css();
t('ブランド: 9枚を同じ色にする', has($css, '.fhs-wrap .fhs-tile{--t-bg:#F1F3F7'), true);
t('ブランド: 個別の色は出さない', has($css, '.fhs-tile-aircon'), false);
t('ブランド: 選択中はアンバー', has($css, '--t-sel-bd:#E8A33D'), true);

/* --- 3. やわらかアンバー --- */
set_pal('amber');
$css = eaf_tile_palette_css();
t('アンバー: 暖色の地',   has($css, '--t-bg:#FBF4E9'), true);
t('アンバー: 選択中の色', has($css, '--t-sel-bd:#DE9A2E'), true);

/* --- 4. カラフル --- */
set_pal('colorful');
$css = eaf_tile_palette_css();
foreach (array_keys($GLOBALS['EAF_PTYPE_LABEL']) as $k) {
    if (!has($css, '.fhs-tile-' . $k . '{')) { t('カラフル: ' . $k . ' の色がある', false, true); }
}
t('カラフル: 9種すべてに色がある', substr_count($css, '--t-bg:'), 9);
/* 色で見分ける配色なので、選択中は自分の色の枠（currentColor）に任せる */
t('カラフル: 選択中の色は上書きしない', has($css, '--t-sel-bd'), false);

/* --- 5. 不正な値は既定へ --- */
set_pal('<script>alert(1)</script>');
t('不正な指定はブランドに落ちる', eaf_opt('tile_palette', 'brand'), 'brand');
set_pal('nonexistent');
t('無い名前もブランドに落ちる', eaf_opt('tile_palette', 'brand'), 'brand');

/* --- 6. フォームのCSSに入っている（★最初の呼び出しでしか見られない） --- */
set_pal('brand');
fake_assets_reset();
eaf_shortcode(array());
$css = fake_inline_style();   /* CSSはキュー側に積まれる */
t('スタイルに配色が入る', has($css, '.fhs-wrap .fhs-tile{--t-bg:#F1F3F7'), true);
t('選択中の見た目が配色に従う', has($css, 'background:var(--t-sel-bg,var(--t-bg))'), true);

/* --- 7. 設定画面に3つ並ぶ --- */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('配色の選択欄がある', has($page, '[tile_palette]'), true);
foreach ($all as $k => $v) t('選択肢が出る: ' . $k, has($page, 'value="' . $k . '"'), true);

/* --- 8. 自己診断 --- */
t('自己診断: 生成関数が空を返していない', strlen(eaf_tile_palette_css()) > 30, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
