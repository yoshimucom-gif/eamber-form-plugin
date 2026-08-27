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
/* --- テストメールの宛先を選べる --- */
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('宛先の入力欄がある',   strpos($page, 'id="eaf-test-to"') !== false, true);
t('送信ボタンがある',     strpos($page, 'id="eaf-test-send"') !== false, true);
t('入力値をURLに載せる',  strpos($page, "'&to=' + encodeURIComponent") !== false, true);
t('「自分宛」固定の文言をやめた', strpos($page, 'テストメールを自分宛に送信') !== false, false);

/* --- 差出人の既定：ドメインの送信専用アドレス（メールボックス不要） --- */
$GLOBALS['FAKE_HOME_URL'] = 'https://www.kai-denkou.com';
update_option(EAF_OPT, eaf_sanitize_options(array()));
t('空欄なら wordpress@ドメイン', eaf_from_address(), 'wordpress@kai-denkou.com');
update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => 'info@kai-denkou.com')));
t('指定があればそれを使う', eaf_from_address(), 'info@kai-denkou.com');

/* --- 通知先は複数指定できる（CF7では2つのGmailに送っていた） --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'notify_email' => 'yamanashi.kaidenkou@gmail.com,yoshimu.com@gmail.com')));
t('2つとも保存される', eaf_opt('notify_email', ''),
  'yamanashi.kaidenkou@gmail.com, yoshimu.com@gmail.com');
t('配列で2件返る', count(eaf_notify_list()), 2);
/* 全角読点・空白区切り・重複・壊れたものが混ざっても拾う */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'notify_email' => 'a@x.test、 b@y.test  a@x.test こわれた')));
t('区切りが揺れても拾う', eaf_opt('notify_email', ''), 'a@x.test, b@y.test');
t('壊れた宛先は落とす', strpos(eaf_opt('notify_email', ''), 'こわれた') !== false, false);
/* 未設定なら管理者アドレスに落ちる */
update_option('admin_email', 'admin@kai-denkou.com');
update_option(EAF_OPT, eaf_sanitize_options(array()));
t('未設定なら管理者宛', eaf_notify_list(), array('admin@kai-denkou.com'));

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
