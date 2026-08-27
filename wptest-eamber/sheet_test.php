<?php
/**
 * スプレッドシートへの転記。
 *
 * ★いちばん大事なのは「転記に失敗しても反響を落とさない」こと。
 *   失敗しても受付は完了し、WordPress側に残り、あとから送り直せる。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/sheet_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }
$ON = array('sheet_on' => '1', 'sheet_url' => 'https://script.google.com/macros/s/AAA/exec',
            'sheet_secret' => 'aiko-2026');

eaf_activate();

/* --- 1. オフのあいだは何も送らない --- */
set_opts(array());
t('既定はオフ',        eaf_flag('sheet_on', false), false);
t('オフなら送らない',  eaf_sheet_ready(), false);
set_opts(array('sheet_on' => '1'));
t('URLが無ければ送らない', eaf_sheet_ready(), false);
set_opts($ON);
t('揃えば送れる状態',  eaf_sheet_ready(), true);

/* --- 2. 送る中身 --- */
$row = array('id' => 7, 'created_at' => '2026-08-27 10:00:00', 'ptype' => 'wiring',
             'address' => '北杜市', 'name' => '山田 太郎', 'tel' => '090-1111-2222',
             'email' => '', 'details' => '■ ご希望の内容 : 新築の電気工事',
             'marketing_opt_in' => 0, 'page_url' => 'https://example.test/a/');
$p = eaf_sheet_payload($row);
t('工事内容は表示名にする', $p['ptype'], '住宅の配線・電気工事全般');
t('合言葉が入る',           $p['secret'], 'aiko-2026');
t('市町村',                 $p['address'], '北杜市');
t('営業同意は文字で',       $p['marketing'], '同意なし');
t('送信元ページも送る',     $p['page_url'], 'https://example.test/a/');

/* --- 3. 実際の送信 --- */
fake_posts_reset();
t('送信は成功扱い', eaf_sheet_send($p), true);
$sent = fake_posts();
t('POST先が設定どおり', $sent[0]['url'], 'https://script.google.com/macros/s/AAA/exec');
/* ★GASは application/json を弾く構成があるので text/plain から試す。中身はJSONのまま */
t('まず text/plain で試す', strpos($sent[0]['args']['headers']['Content-Type'], 'text/plain') === 0, true);
t('中身はJSON', json_decode($sent[0]['args']['body'], true) !== null, true);
$body = json_decode($sent[0]['args']['body'], true);
t('本文に工事内容が入る', $body['ptype'], '住宅の配線・電気工事全般');
t('転送を追う設定', $sent[0]['args']['redirection'] >= 1, true);

/* --- 4. 失敗の伝え方 --- */
$GLOBALS['FAKE_POST_RESPONSE'] = array('code' => 200, 'body' => '{"ok":false,"error":"secret"}');
$r = eaf_sheet_send($p);
t('合言葉違いは分かる文言', is_string($r) && strpos($r, '合言葉') !== false, true);
$GLOBALS['FAKE_POST_RESPONSE'] = array('code' => 500, 'body' => 'boom');
$r = eaf_sheet_send($p);
t('HTTPエラーも文字で返す', is_string($r) && strpos($r, 'HTTP 500') !== false, true);
$GLOBALS['FAKE_POST_RESPONSE'] = new WP_Error('http', 'つながりません');
$r = eaf_sheet_send($p);
t('通信断も文字で返す', $r, 'つながりません');
fake_posts_reset();

/* --- 5. 保存済みの1件を送ると転記済みになる --- */
global $wpdb;
$T = $wpdb->prefix . 'eamber_form_leads';
$wpdb->insert($T, array('created_at' => '2026-08-27 11:00:00', 'email' => '', 'ptype' => 'aircon',
                        'address' => '甲府市', 'name' => '鈴木', 'tel' => '090-3333-4444',
                        'details' => '', 'marketing_opt_in' => 0, 'page_url' => '', 'sheet_sent' => 0));
$id = (int) $wpdb->insert_id;
t('未転記が1件', eaf_sheet_unsent_count(), 1);
$stored = $wpdb->get_row("SELECT * FROM `$T` WHERE id = $id", ARRAY_A);
t('送って成功', eaf_sheet_send_row($stored), true);
t('未転記が0件になる', eaf_sheet_unsent_count(), 0);
t('成功したらエラー記録は消える', get_option('eaf_sheet_last_error'), false);

/* --- 6. 失敗したら未転記のまま残り、理由が残る --- */
$wpdb->insert($T, array('created_at' => '2026-08-27 12:00:00', 'email' => '', 'ptype' => 'fan',
                        'address' => '韮崎市', 'name' => '佐藤', 'tel' => '090-5555-6666',
                        'details' => '', 'marketing_opt_in' => 0, 'page_url' => '', 'sheet_sent' => 0));
$id2 = (int) $wpdb->insert_id;
$GLOBALS['FAKE_POST_RESPONSE'] = new WP_Error('http', 'タイムアウト');
$stored2 = $wpdb->get_row("SELECT * FROM `$T` WHERE id = $id2", ARRAY_A);
t('失敗は文字で返る', eaf_sheet_send_row($stored2), 'タイムアウト');
t('★未転記のまま残る', eaf_sheet_unsent_count(), 1);
t('理由が控えられる', strpos((string) get_option('eaf_sheet_last_error'), 'タイムアウト') !== false, true);
unset($GLOBALS['FAKE_POST_RESPONSE']);

/* --- 7. 貼り付け用スクリプトに合言葉が入る --- */
$code = eaf_sheet_gas_code();
t('スクリプトに合言葉が埋まる', strpos($code, '"aiko-2026"') !== false, true);
t('doPostがある',               strpos($code, 'function doPost') !== false, true);
t('見出し行を作る',             strpos($code, '受付日時') !== false, true);
/* ★送る項目を増やしたのにスクリプト側へ書き足し忘れると、その値は黙って消える
     （フリガナで実際に起きた）。payload の全キーが script に出ているかを突きつける。 */
foreach (array_keys(eaf_sheet_payload(array())) as $k) {
    if ($k === 'secret') continue;
    t('スクリプトが ' . $k . ' を書いている', strpos($code, 'data.' . $k) !== false, true);
}
/* 列は固定＝項目の出し入れでずれない。見出しの数と書き込む値の数が一致すること */
preg_match('/sh\.appendRow\(\[(.*?)\]\);\s*\}/s', $code, $h);
preg_match('/sh\.appendRow\(\[\s*
(.*?)
\s*\]\);/s', $code, $v);
$head_n = preg_match_all("/'[^']*'/", $h[1]);          // 見出しの個数
$val_n  = preg_match_all('/data\.[a-z_]+/', $v[1]);    // 書き込む値の個数
t('見出しの数と値の数が合う', $head_n, $val_n);
t('列は16', $head_n, 16);

/* --- 8. 設定画面に連携タブがある --- */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('連携タブがある',   strpos($page, 'data-tab="link"') !== false, true);
t('タブ名が「連携」', strpos($page, '>連携</a>') !== false, true);
t('接続テストがある', strpos($page, 'action=eaf_sheet_test') !== false, true);
t('再送がある',       strpos($page, 'action=eaf_sheet_resend') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
