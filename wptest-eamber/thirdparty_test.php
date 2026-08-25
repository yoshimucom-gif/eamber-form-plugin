<?php
/**
 * 第三者提供の機構が「無い」ことの検査（機能削除の退行防止）。
 *
 * 運営＝施工＝同じ会社の自社サイトに置くフォームなので、
 * 本気査定にあった「提携先へ提供する」設定・同意文はまるごと削除した。
 * 同意文は「ご本人の同意なく第三者に提供することはありません」に固定される。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/tp_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

/* --- 1. 旧設定キーは保存されない（残っていても無視される） --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'third_party' => '1', 'third_party_name' => '悪意ある提供先', 'third_party_url' => 'https://x.example/',
)));
$o = get_option(EAF_OPT, array());
t('third_party は保存されない',      array_key_exists('third_party', $o), false);
t('third_party_name は保存されない', array_key_exists('third_party_name', $o), false);
t('third_party_url は保存されない',  array_key_exists('third_party_url', $o), false);

/* --- 2. フォームの同意文は「提供しない」で固定 --- */
$html = eaf_shortcode(array());
t('「第三者に提供することはありません」が出る', strpos($html, 'ご本人の同意なく第三者に提供することはありません') !== false, true);
t('提供する旨の文言は出ない', strpos($html, 'にご入力内容') !== false, false);
t('「提供を含む」の同意文が出ない', strpos($html, 'への提供を含む') !== false, false);

/* --- 3. 設定画面に「個人情報」タブが無い --- */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('個人情報タブが無い', strpos($page, 'data-tab="privacy"') !== false, false);
t('提供先の入力欄が無い', strpos($page, 'third_party_name') !== false, false);

/* --- 自己診断 --- */
t('自己診断: 同意文の検査キーを検出できる', strpos('<p>ご本人の同意なく第三者に提供することはありません</p>', 'ご本人の同意なく第三者に提供することはありません') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
