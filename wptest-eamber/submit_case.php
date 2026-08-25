<?php
/**
 * フォーム送信（eaf_ajax）を1件だけ実行する子プロセス。
 * wp_send_json が最後に exit するため、同じプロセスでは続きを検査できない。
 *
 * 引数: 1=POST内容を書いたJSONファイル 2=状態ファイル（ドライバと共有）
 * 出力: eaf_ajax のJSON応答
 */
$GLOBALS['FAKE_STATE_FILE'] = $argv[2];
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$post = json_decode(file_get_contents($argv[1]), true);
foreach ($post as $k => $v) $_POST[$k] = $v;
$_REQUEST['nonce'] = $_POST['nonce'] = wp_create_nonce('eamber_form');
$_POST['eaf_elapsed'] = '9999';   // ボット判定（経過時間）を通す

eaf_ajax();
