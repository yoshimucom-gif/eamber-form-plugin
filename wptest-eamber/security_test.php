<?php
/**
 * 個人情報を盗られないか、実際に攻撃してみる検査。
 *
 * 守るもの＝申込テーブル（お名前・電話・メール・物件住所）。
 * 「権限チェックを書いた」ではなく「書いたものが実際に止めるか」を確かめる。
 * すべてのシナリオを子プロセスで走らせる（途中で exit する経路があるため）。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/sec_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';
foreach ($GLOBALS['FAKE_ACTIVATE'] as $cb) call_user_func($cb);   // テーブル作成

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
/** 1シナリオを子プロセスで実行し、出力を返す */
function run($fn, $caps = '', $strict = 0, $nonce_action = '') {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/sec_case.php')
         . ' ' . escapeshellarg($fn) . ' ' . escapeshellarg($caps)
         . ' ' . escapeshellarg((string)$strict) . ' ' . escapeshellarg($nonce_action) . ' 2>&1';
    $out = (string) shell_exec($cmd);
    // ヘッダー送信の警告はテスト環境固有のノイズなので落とす
    return preg_replace('/^(PHP )?Warning:.*$/m', '', $out);
}
$blocked = function ($out) { return strpos($out, '権限がありません') !== false; };

/* --- 盗まれたら困るデータを仕込む --- */
global $wpdb;
$T = $wpdb->prefix . 'eamber_form_leads';
$wpdb->insert($T, array(
    'created_at' => '2026-08-20 10:00:00', 'name' => '被害 太郎', 'tel' => '090-0000-1111',
    'email' => 'higai@example.test', 'ptype' => 'mansion', 'address' => '岡山県岡山市北区表町9-9-9',
    'details' => '', 'marketing_opt_in' => 0,
));
$SECRET = '090-0000-1111';   // これが出てきたら失格（ASCIIなのでShift_JIS変換後も一致する）
t('自己診断: 仕込んだデータは実在する',
  strpos(json_encode($wpdb->get_results("SELECT * FROM $T")), $SECRET) !== false, true);

echo "\n--- 1. 未ログインの訪問者 ---\n";
$o = run('eaf_export_leads');
t('CSVエクスポートは止まる',        $blocked($o), true);
t('CSVに電話番号が出ない',          strpos($o, $SECRET) !== false, false);
t('リード削除は止まる',             $blocked(run('eaf_delete_lead')), true);
t('テスト送信は止まる',             $blocked(run('eaf_test_mail')), true);
t('更新チェックは止まる',           $blocked(run('eaf_check_update')), true);
$o = run('eaf_leads_page');
t('★申込一覧を直接呼んでも漏れない', strpos($o, $SECRET) !== false, false);
$o = run('eaf_settings_page');
t('★設定画面を直接呼んでも漏れない', strpos($o, $SECRET) !== false, false);

echo "\n--- 2. 購読者（ログインできる一般会員） ---\n";
$o = run('eaf_export_leads', 'read');
t('CSVエクスポートは止まる',        $blocked($o), true);
t('CSVに電話番号が出ない',          strpos($o, $SECRET) !== false, false);
$o = run('eaf_leads_page', 'read');
t('★申込一覧を直接呼んでも漏れない', strpos($o, $SECRET) !== false, false);
t('★リード削除も止まる',            $blocked(run('eaf_delete_lead', 'read')), true);

echo "\n--- 3. 管理者でもリンクの偽造（CSRF）は通さない ---\n";
$o = run('eaf_export_leads', 'manage_options', 1);
t('nonceなしのCSVは止まる',         strpos($o, '有効期限') !== false, true);
t('nonceなしでは中身が出ない',      strpos($o, $SECRET) !== false, false);
$o = run('eaf_export_leads', 'manage_options', 1, 'eaf_export_leads');
t('★正しいnonceならCSVは出る（検査が空振りしていない証拠）', strpos($o, $SECRET) !== false, true);

echo "\n--- 4. 送信フォームそのもの ---\n";
t('nonceなしの送信は拒否される',    trim(run('eaf_ajax')), '-1');

echo "\n--- 5. 保存された値が悪さをしないか ---\n";
$wpdb->insert($T, array(
    'created_at' => '2026-08-20 11:00:00',
    'name'    => '<script>alert(1)</script>',
    'tel'     => '=cmd|\' /C calc\'!A1',
    'email'   => 'x@example.test', 'ptype' => 'mansion',
    'address' => '" onmouseover="alert(2)',
    'details' => '', 'marketing_opt_in' => 0,
));
$o = run('eaf_leads_page', 'manage_options');
t('一覧に生の<script>が出ない',     strpos($o, '<script>alert(1)</script>') !== false, false);
t('エスケープされて出ている',       strpos($o, '&lt;script&gt;') !== false, true);
t('属性を抜け出す"が無害化',        strpos($o, '" onmouseover=') !== false, false);
$o = run('eaf_export_leads', 'manage_options', 1, 'eaf_export_leads');
t('CSVの数式は先頭に\'が付く',       strpos($o, "'=cmd") !== false, true);

echo "\n--- 6. 保存エラーの記録に個人情報を持ち越さない ---\n";
eaf_record_db_error("Duplicate entry 'higai@example.test' for key 'email'");
t('全ページで読まれる設定にはしない', fake_autoload_of('eaf_last_db_error'), 'no');
t('記録は長すぎない',                 strlen(get_option('eaf_last_db_error')) <= 340, true);

echo "\n--- 7. httpのまま運用していないか ---\n";
$GLOBALS['FAKE_IS_ADMIN'] = true;
$warn = function () { ob_start(); do_action('admin_notices'); return ob_get_clean(); };
$GLOBALS['FAKE_HOME_URL'] = 'http://example.test';
$GLOBALS['FAKE_CAPS'] = array('manage_options');
t('httpなら警告が出る',   strpos($warn(), 'https ではありません') !== false, true);
$GLOBALS['FAKE_HOME_URL'] = 'https://example.test';
t('httpsなら出ない',      strpos($warn(), 'https ではありません') !== false, false);
$GLOBALS['FAKE_HOME_URL'] = 'http://example.test';
$GLOBALS['FAKE_CAPS'] = array();
t('訪問者には見せない',   strpos($warn(), 'https ではありません') !== false, false);
unset($GLOBALS['FAKE_CAPS']);

echo "\n--- 8. 保存値でテーブルを壊せないか ---\n";
$evil = "'; DROP TABLE {$T}; --";
$wpdb->insert($T, array(
    'created_at' => '2026-08-20 12:00:00', 'name' => $evil, 'email' => 'y@example.test',
    'ptype' => 'mansion', 'address' => $evil, 'details' => '', 'marketing_opt_in' => 0,
));
t('テーブルは消えていない',         (int)$wpdb->get_var("SELECT COUNT(*) FROM $T") >= 3, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
