<?php
/**
 * 自動更新チェッカーの実HTTP検証。
 * GitHub の update.json を実際に取りに行き、
 *  ・管理画面の外（WP-Cron相当）でも更新エントリが注入されるか
 *  ・同じバージョンなら「更新なし」になるか
 * を確認する。※ 過去に is_admin() で囲んで「自動更新を有効化してもcronで更新が入らない」罠を踏んだ箇所。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/updater_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);

/* wp_remote_get を本物のHTTPに差し替える（wp_stub より先に定義しておく） */
function wp_remote_get($url, $args = array()) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_USERAGENT => 'WordPress/6.7; test',
        // ポータブルPHPにはCAバンドルが無いため、Windowsの証明書ストアを使う
        // （本番のWordPressは自前のCAバンドルを持っているので、この指定は検証環境専用）
        CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) return new WP_Error('http', 'curl failed');
    return array('code' => $code, 'body' => $body);
}
function wp_remote_retrieve_response_code($r) { return is_array($r) ? $r['code'] : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) ? $r['body'] : ''; }

require __DIR__ . '/wp_stub.php';

/* WP-Cron 相当（管理画面の外）を再現 */
define('DOING_CRON', true);
$GLOBALS['FAKE_IS_ADMIN'] = false;

/* プラグインヘッダーからバージョンを読む WP 関数 */
function get_plugin_data($file, $markup = true, $translate = true) {
    $src = file_get_contents($file);
    preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $src, $m);
    return array('Version' => isset($m[1]) ? $m[1] : '');
}

require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($name, $got, $want) {
    global $ng; $ok = ($got === $want); if (!$ok) $ng++;
    printf("%s %s\n     got=%s want=%s\n", $ok ? 'OK  ' : 'NG  ', $name, var_export($got, true), var_export($want, true));
}

t('cron中(管理画面外)でもチェッカーが読み込まれている', class_exists('EAF_Updater'), true);
t('更新チェックのフックが登録されている', has_action('pre_set_site_transient_update_plugins'), true);

$updater = new EAF_Updater(dirname(__DIR__) . '/eamber-form/eamber-form.php', EAF_UPDATE_URL);
$basename = 'eamber-form/eamber-form.php';

/* 1) インストール済みが最新（1.0.0）＝更新なし */
$tr = $updater->check_for_update((object)array('response' => array(), 'no_update' => array()));
t('同バージョンなら更新エントリは出ない', isset($tr->response[$basename]), false);
t('no_update に入る（正常にサーバーへ到達できている証拠）', isset($tr->no_update[$basename]), true);
if (isset($tr->no_update[$basename])) {
    echo "     配信中のバージョン: " . $tr->no_update[$basename]->new_version . "\n";
}

/* 2) 配信側が新しい場合＝更新エントリが入るか（プラグインヘッダーを古く見せて再現） */
function get_plugin_data_old() {}   // 参照用
$GLOBALS['FAKE_OLD'] = true;
$updater2 = new EAF_Updater(__DIR__ . '/fake_old_plugin.php', EAF_UPDATE_URL);
file_put_contents(__DIR__ . '/fake_old_plugin.php', "<?php\n/**\n * Plugin Name: dummy\n * Version: 0.9.0\n */\n");
$tr2 = $updater2->check_for_update((object)array('response' => array(), 'no_update' => array()));
$key = basename(__DIR__) . '/fake_old_plugin.php';
$entry = isset($tr2->response[$key]) ? $tr2->response[$key] : null;
t('古い版には更新エントリが入る', $entry !== null, true);
if ($entry) {
    // 配信中(update.json)とプラグイン本体のバージョンが食い違っていたら build.py の実行漏れ
    t('配信バージョン = 本体バージョン', $entry->new_version, EAF_VER);
    t('zipのダウンロードURLが入っている', strpos($entry->package, 'eamber-form.zip') !== false, true);
    echo "     package: " . $entry->package . "\n";
}
@unlink(__DIR__ . '/fake_old_plugin.php');

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
exit($ng ? 1 : 0);
