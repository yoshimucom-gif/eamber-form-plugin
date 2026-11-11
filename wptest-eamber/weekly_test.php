<?php
/**
 * 週に1度のまとめメール。
 *
 * ★「1件も届かなかった週は送らない」が肝。ここが効いていないと、
 *   毎週「0件です」が届き、読まれなくなって、肝心の届いた週まで見過ごされる。
 * ★見回りは毎時走る。同じ日に二度送らない歯止めが無いと、
 *   指定時刻から日付が変わるまで毎時届く。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/weekly_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
/** 送ったメールを数え直す */
function mails() {
    fake_state();
    return isset($GLOBALS['FAKE_STATE']['mails']) ? $GLOBALS['FAKE_STATE']['mails'] : array();
}
function mails_clear() {
    fake_state(); $GLOBALS['FAKE_STATE']['mails'] = array(); fake_save();
}

global $wpdb;
eaf_activate();
$table = $wpdb->prefix . 'eamber_form_leads';

$o = get_option(EAF_OPT, array());
$o['notify_email'] = 'a@example.test, b@example.test';
$o['from_email']   = 'info@example.test';
$o['weekly_on']    = '1';
$o['weekly_day']   = '1';   // 月曜
$o['weekly_hour']  = '9';
update_option(EAF_OPT, $o);

/* 2026年11月11日(水) 12時 を「いま」とする */
$GLOBALS['FAKE_NOW'] = mktime(12, 0, 0, 11, 11, 2026);

echo "--- 1. 毎時の見回りが仕掛けてある ---\n";
fake_cron_reset();
eaf_schedule_hourly();
$c = wp_next_scheduled('eaf_hourly');
t('見回りが仕掛けてある', is_array($c), true);
t('1時間おき', is_array($c) ? $c['every'] : '', 'hourly');
/* 二度目を呼んでも二重に仕掛けない */
eaf_schedule_hourly();
t('二重に仕掛けない', count($GLOBALS['FAKE_CRON']), 1);

echo "\n--- 2. 1件も届いていない週は送らない ---\n";
mails_clear();
$n = eaf_send_weekly_digest();
t('送らない',       count(mails()), 0);
t('0件と返ってくる', $n, 0);

echo "\n--- 3. 届いていれば送る ---\n";
/* 8日前（範囲の外）と、3日前・1日前（範囲の中） */
$wpdb->insert($table, array(
    'created_at' => date('Y-m-d H:i:s', $GLOBALS['FAKE_NOW'] - 8 * 86400),
    'name' => '範囲外 太郎', 'tel' => '055-000-0000', 'ptype' => 'aircon', 'address' => '甲府市',
));
$wpdb->insert($table, array(
    'created_at' => date('Y-m-d H:i:s', $GLOBALS['FAKE_NOW'] - 3 * 86400),
    'name' => '山田 太郎', 'tel' => '090-0000-1111', 'ptype' => 'outlet', 'address' => '南アルプス市',
    'detail' => 'コンセントを増やしたいです。',
));
$wpdb->insert($table, array(
    'created_at' => date('Y-m-d H:i:s', $GLOBALS['FAKE_NOW'] - 1 * 86400),
    'company' => '検査用ホテル', 'name' => '鈴木 次郎', 'tel' => '055-000-2222',
    'ptype' => 'fan', 'address' => '富士河口湖町', 'email' => 'asari@example.test',
));

$rows = eaf_weekly_leads();
t('直近7日だけを拾う', count($rows), 2);

mails_clear();
$n = eaf_send_weekly_digest();
t('2件として送る', $n, 2);

$ms = mails();
t('宛先の数だけ送る',      count($ms), 2);
t('1通ごとの宛先は1件だけ', (count($ms) === 2 && $ms[0]['to'] === 'a@example.test' && $ms[1]['to'] === 'b@example.test'), true);

$subj = $ms[0]['subject'];
$body = $ms[0]['body'];
t('件名に件数が入る', strpos($subj, '2件') !== false, true);
t('件名が分かる形',   strpos($subj, '【今週のお問い合わせ】') !== false, true);

echo "\n--- 4. 本文に、折り返すのに要るものが揃っている ---\n";
t('お名前',        strpos($body, '山田 太郎') !== false, true);
t('会社名',        strpos($body, '検査用ホテル') !== false, true);
t('工事内容は正式名', strpos($body, '換気扇') !== false, true);
t('市町村',        strpos($body, '南アルプス市') !== false, true);
t('電話番号',      strpos($body, '090-0000-1111') !== false, true);
t('メール',        strpos($body, 'asari@example.test') !== false, true);
t('曜日つきの日時', preg_match('/\d+月\d+日\([日月火水木金土]\)/u', $body) === 1, true);
t('反響一覧への案内', strpos($body, '反響一覧') !== false, true);
t('範囲外は載せない', strpos($body, '範囲外 太郎') !== false, false);

echo "\n--- 5. 指定の曜日・時刻にだけ送る ---\n";
/* いまは水曜。月曜指定なので送られない */
delete_option('eaf_weekly_sent_date');
mails_clear();
eaf_weekly_cron();
t('曜日が違えば送らない', count(mails()), 0);

/* 月曜の8時＝まだ時刻前 */
$GLOBALS['FAKE_NOW'] = mktime(8, 0, 0, 11, 9, 2026);   // 2026-11-09 は月曜
t('この日は月曜', (int) date('w', $GLOBALS['FAKE_NOW']), 1);
mails_clear();
eaf_weekly_cron();
t('時刻前なら送らない', count(mails()), 0);

/* 月曜の9時＝送る */
$GLOBALS['FAKE_NOW'] = mktime(9, 0, 0, 11, 9, 2026);
mails_clear();
eaf_weekly_cron();
t('曜日と時刻が合えば送る', count(mails()), 2);

/* 同じ日に、もう一度見回りが走っても送らない */
$GLOBALS['FAKE_NOW'] = mktime(10, 0, 0, 11, 9, 2026);
mails_clear();
eaf_weekly_cron();
t('同じ日に二度送らない', count(mails()), 0);

/* 週まとめをOFFにしたら送らない */
$o = get_option(EAF_OPT, array()); $o['weekly_on'] = '0'; update_option(EAF_OPT, $o);
delete_option('eaf_weekly_sent_date');
$GLOBALS['FAKE_NOW'] = mktime(9, 0, 0, 11, 9, 2026);
mails_clear();
eaf_weekly_cron();
t('OFFなら送らない', count(mails()), 0);

echo "\n--- 6. 設定は範囲の中に収める ---\n";
/* ★範囲外の値が入ると、見回りの条件に永久に当たらず
     「設定したのに一度も届かない」状態になる。 */
$v = eaf_sanitize_options(array('weekly_day' => '9', 'weekly_hour' => '99'));
t('曜日は6まで', $v['weekly_day'], '6');
t('時刻は23まで', $v['weekly_hour'], '23');
$v = eaf_sanitize_options(array('weekly_day' => '-3', 'weekly_hour' => '-1'));
t('曜日は0から', $v['weekly_day'], '0');
t('時刻は0から', $v['weekly_hour'], '0');
$v = eaf_sanitize_options(array('weekly_on' => '1', 'weekly_day' => '5', 'weekly_hour' => '17'));
t('選んだ値は残る', $v['weekly_day'] . '/' . $v['weekly_hour'] . '/' . $v['weekly_on'], '5/17/1');

echo "\n--- 7. 設定画面に出ている ---\n";
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
$GLOBALS['FAKE_IS_ADMIN'] = false;
t('週まとめの欄がある',   strpos($page, '週に1度のまとめ') !== false, true);
t('曜日を選べる',         strpos($page, 'weekly_day') !== false, true);
t('時刻を選べる',         strpos($page, 'weekly_hour') !== false, true);
t('0件の週は送らないと書いてある', strpos($page, '1件も届かなかった週は送りません') !== false, true);
t('いま送って試せる',     strpos($page, 'eaf_send_weekly') !== false, true);

echo "\n--- 8. 自己診断 ---\n";
t('自己診断: メールを数えられている', is_array(mails()), true);
t('自己診断: 時刻を動かせている', date('Y-m-d', current_time('timestamp')), '2026-11-09');

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
