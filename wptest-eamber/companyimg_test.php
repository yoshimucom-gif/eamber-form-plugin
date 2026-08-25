<?php
/**
 * フォーム下部の「対応会社」欄と会社画像が「無い」ことの検査（機能削除の退行防止）。
 *
 * 本気査定では運営（サイト）と査定会社が別だったため、
 * 「どこの会社に連絡先を渡すのか」を示す会社欄・会社画像が必要だった。
 * eamber-form は運営＝施工＝同じ会社の自社サイトに置くフォームなので、
 * サイト自体が会社を示している＝会社欄はまるごと削除した。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/cimg_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

/* 旧設定を全部入れても、会社欄は現れない */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'operator_address' => '山梨県甲府市○○1-2-3',
    'operator_contact' => '055-000-0000', 'operator_email' => 'info@example.test',
    'company_image' => 'https://example.test/logo.png', 'operator_url' => 'https://example.test/',
)));
foreach (array('default' => array(), 'card' => array('design' => 'card'), 'compact' => array('design' => 'compact')) as $name => $atts) {
    $html = eaf_shortcode($atts);
    t($name . ': 会社欄が出ない',   strpos($html, 'fhs-operator') !== false, false);
    t($name . ': 会社画像が出ない', strpos($html, 'fhs-opimg') !== false, false);
}

/* 旧設定キーは保存されない */
$o = get_option(EAF_OPT, array());
t('company_image は保存されない', array_key_exists('company_image', $o), false);
t('operator_url は保存されない',  array_key_exists('operator_url', $o), false);
t('operator_name は保存されない', array_key_exists('operator_name', $o), false);

/* 設定画面にも会社画像の欄が無い（ティザーのアイコン logo_url は残る） */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('対応会社タブが無い',       strpos($page, 'data-tab="company"') !== false, false);
t('会社画像の欄が無い',       strpos($page, 'company_image') !== false, false);
t('ティザーのアイコン欄は残る', strpos($page, 'logo_url') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
