<?php
/**
 * メールの差出人まわり。
 * ★差出人にフリーメールを入れると送れない。ここを取り違えると
 *   「反響は保存されているのに誰も気づかない」状態になる。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/mail_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';
$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
foreach (array('a@gmail.com' => 'gmail.com', 'b@YAHOO.CO.JP' => 'yahoo.co.jp',
               'c@docomo.ne.jp' => 'docomo.ne.jp', 'd@icloud.com' => 'icloud.com') as $m => $d) {
    t('フリーメールと判定: ' . $m, eaf_free_mail_domain($m), $d);
}
foreach (array('info@kai-denkou.com', 'x@example.co.jp', '', 'こわれた') as $m) {
    t('自社ドメイン等は素通し: ' . ($m === '' ? '(空)' : $m), eaf_free_mail_domain($m), '');
}
$GLOBALS['FAKE_IS_ADMIN'] = true;
update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => 'shop@gmail.com')));
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('差出人がGmailなら警告する', strpos($page, '差出人が gmail.com になっています') !== false, true);
update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => 'info@kai-denkou.com')));
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('自社ドメインなら警告しない', strpos($page, 'になっています。</strong>') !== false, false);
t('通知先はGmailで良いと書いてある', strpos($page, 'Gmailで構いません') !== false, true);
echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
