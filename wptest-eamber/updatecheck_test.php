<?php
/**
 * 設定画面の「更新を確認」ボタン。
 * WordPressの自動チェックは最大12時間おきなので、その場で確認できる導線を用意している。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/uc_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();

t('現在のバージョンを表示', strpos($page, EAF_VER) !== false, true);
t('「更新を確認」ボタンがある', strpos($page, 'action=eaf_check_update') !== false, true);
t('nonce付きのリンク', strpos($page, '_wpnonce=') !== false, true);
t('処理が登録されている', has_action('admin_post_eaf_check_update'), true);

// 最新版が分からないときは「最新です」と言い切らない
t('未取得なら断定しない', strpos($page, '（最新です）') !== false, false);

// 更新ありの状態を作る
$me = 'eamber-form/eamber-form.php';
set_site_transient('update_plugins', (object) array(
    'response' => array($me => (object) array('new_version' => '99.0.0')),
    'no_update' => array(),
));
t('最新版を読み取れる', eaf_latest_version(), '99.0.0');
ob_start(); eaf_settings_page(); $page2 = ob_get_clean();
t('新版があると知らせる',     strpos($page2, '最新は 99.0.0 です') !== false, true);
t('更新画面への導線を出す',   strpos($page2, 'プラグイン画面で更新する') !== false, true);

// 最新の状態
set_site_transient('update_plugins', (object) array(
    'response' => array(),
    'no_update' => array($me => (object) array('new_version' => EAF_VER)),
));
t('no_updateからも読める', eaf_latest_version(), EAF_VER);
ob_start(); eaf_settings_page(); $page3 = ob_get_clean();
t('最新なら「最新です」',     strpos($page3, '（最新です）') !== false, true);
t('更新の導線は出さない',     strpos($page3, 'プラグイン画面で更新する') !== false, false);

// 自己診断
t('自己診断: 検査が空振りしていない', strpos($page3, 'お問い合わせフォーム 設定') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
