<?php
/**
 * 攻撃シナリオを1件だけ実行する子プロセス。
 *
 * CSV出力や check_ajax_referer は最後に exit するため、同じプロセスでは
 * 続きを検査できない。1件ずつ別プロセスで走らせて、出力だけを受け取る。
 *
 * 引数: 1=呼ぶ関数名 2=権限(カンマ区切り。空文字＝未ログイン) 3=nonceを厳格に見るか(1/0)
 *       4=正しいnonceを付けるaction名（空なら付けない）
 *
 * ※ JSONを引数で渡すとWindowsの escapeshellarg が " を壊し、権限が既定に戻って
 *   「検査したつもりで素通り」になる。だからカンマ区切りにしている。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/sec_state.json';   // ドライバが仕込んだデータを共有する
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$fn    = $argv[1];
$caps  = ($argv[2] === '') ? array() : explode(',', $argv[2]);
$GLOBALS['FAKE_CAPS'] = $caps;
if (!empty($argv[3])) $GLOBALS['FAKE_STRICT_NONCE'] = true;
$action = $argv[4] ?? '';
if ($action !== '') { $_REQUEST['_wpnonce'] = wp_create_nonce($action); $_REQUEST['nonce'] = wp_create_nonce($action); }

call_user_func($fn);
