<?php
/* 設定画面（連携タブ）を書き出して、実物を目で見る（使い捨て） */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/_ds_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$o = get_option(EAF_OPT, array());
$o['sheet_on']  = '1';
$o['sheet_url'] = 'https://script.google.com/macros/s/AKfycb.../exec';
$o['sheet_secret'] = 'eamber';
update_option(EAF_OPT, $o);

$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $html = ob_get_clean();
$GLOBALS['FAKE_IS_ADMIN'] = false;

/* 連携タブの中身だけ取り出す */
if (preg_match('#<div class="fhs-tabpanel" data-tab="link".*?>(.*?)</div>\s*<div class="fhs-tabpanel"#s', $html, $m)) {
    $html = '<div class="wrap"><h1>電気工事反響フォーム 設定</h1>'
          . '<h2 class="nav-tab-wrapper"><a href="#" class="nav-tab">基本設定</a>'
          . '<a href="#" class="nav-tab">入力項目</a><a href="#" class="nav-tab">自動返信メール</a>'
          . '<a href="#" class="nav-tab">デザイン</a><a href="#" class="nav-tab nav-tab-active">連携</a>'
          . '<a href="#" class="nav-tab">ショートコード</a></h2>' . $m[1] . '</div>';
}
file_put_contents(__DIR__ . '/_settings_body.html', $html);
echo "ok\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
