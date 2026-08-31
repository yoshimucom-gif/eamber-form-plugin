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

/* --- 通知先はカンマ区切りで複数書ける（入力欄の側も許していること） -------
   ★<input type="email"> のままだとブラウザが1件しか認めず、
     「@に続く文字列に記号「,」を使用しないでください」と出て保存できない。
     HTMLの multiple を付けて初めて、カンマ区切りが正式な書き方になる。 */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page2 = ob_get_clean();
t('★通知先は複数書ける入力欄',
  preg_match('/<input type="email" multiple name="[^"]*\[notify_email\]"/', $page2) === 1, true);
t('書き方の例に2件並べてある', strpos($page2, 'staff@example.com, tantou@example.com') !== false, true);
/* 送信元は1件だけ。ここに multiple が付くと、複数書けると誤解させる */
t('送信元は1件だけの入力欄',
  preg_match('/<input type="email" multiple name="[^"]*\[from_email\]"/', $page2) === 1, false);
/* 全角読点や空白で書かれても、離れた時点で半角カンマに整える */
t('区切りを整える処理がある', strpos($page2, "split(/[\s,]+/)") !== false, true);
$GLOBALS['FAKE_IS_ADMIN'] = false;

/* --- 送信元メール：「空欄にしたらどうなるか」の案内 ------------------------
   ★ここは実際に間違えた箇所。設定画面の「空欄なら◯◯として送ります」に
     eaf_from_address() を出していたため、欄がGmailで埋まっていると
     「空欄にすればGmailで送ります」という嘘の案内になっていた。 */
update_option('home', 'https://kai-denkou.com/stargazer/');
update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => '')));
t('空欄ならサイトのドメインで送る', eaf_from_address(), 'wordpress@kai-denkou.com');
t('空欄時の案内も同じ',             eaf_default_from_address(), 'wordpress@kai-denkou.com');

update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => 'yoshimu.com@gmail.com')));
t('設定があればそれを使う', eaf_from_address(), 'yoshimu.com@gmail.com');
t('★空欄時の案内は設定に引きずられない', eaf_default_from_address(), 'wordpress@kai-denkou.com');

$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('設定画面の案内は既定の住所で出す',
  strpos($page, '<code>wordpress@kai-denkou.com</code> として送ります') !== false, true);
t('★「空欄ならGmailで送る」とは書かない',
  strpos($page, '<code>yoshimu.com@gmail.com</code> として送ります') !== false, false);
t('赤い警告の逃げ道も既定の住所',
  strpos($page, '<code>wordpress@kai-denkou.com</code> として送られます') !== false, true);

/* --- 送信に失敗した理由を控えて、画面に出す ------------------------------
   ★wp_mail() は false を返すだけ。理由が分からないと「差出人が悪いのか
     サーバーが送れないのか」を切り分けられず、当てずっぽうしか出せない。 */
delete_option('eaf_last_mail_error');
$html = eaf_test_mail_failure_html();
t('理由が取れないときはその旨を書く',
  strpos($html, 'エラーの詳細は取得できませんでした') !== false, true);

eaf_mail_error_capture(new WP_Error('wp_mail_failed', 'Could not instantiate mail function.'));
$e = get_option('eaf_last_mail_error');
t('エラーを控える', is_array($e) && $e['msg'] === 'Could not instantiate mail function.', true);
t('そのときの差出人も残す', is_array($e) ? $e['from'] : '', 'yoshimu.com@gmail.com');

$html = eaf_test_mail_failure_html();
t('画面に実際のエラーを出す', strpos($html, 'Could not instantiate mail function.') !== false, true);
t('いまの送信の状態も並べる', strpos($html, 'PHPのmail()関数') !== false, true);
t('SMTPプラグインの有無も出す', strpos($html, 'SMTPプラグイン') !== false, true);
t('空欄にする案内は既定の住所',
  strpos($html, '<code>wordpress@kai-denkou.com</code> として送られます') !== false, true);

/* 他のプラグインが WP_Error 以外を流してきても壊れない */
eaf_mail_error_capture('ただの文字列');
$e2 = get_option('eaf_last_mail_error');
t('WP_Error以外は無視する', is_array($e2) ? $e2['msg'] : '', 'Could not instantiate mail function.');
$GLOBALS['FAKE_IS_ADMIN'] = false;

/* 自己診断 */
t('自己診断: 失敗時の本文が空ではない', strlen($html) > 100, true);
t('自己診断: 設定画面を描けている', strpos($page, '送信元メール') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
