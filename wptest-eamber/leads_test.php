<?php
/**
 * 反響一覧ページ（管理画面）。
 *
 * ★ここは「1件でも反響が入ると開けなくなる」事故が起きうる場所。
 *   行を組み立てている printf は、書式の %s の数と引数の数がずれると
 *   PHP 8 では ArgumentCountError で即座に落ちる（PHP 7 は警告で済んでいた）。
 *   見出し（th）とデータ（td）の数が揃っていることも合わせて見る。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/leads_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
/** タグの数を数える（属性つきも拾う） */
function count_cells($html, $tag) {
    return preg_match_all('#<' . $tag . '(\s[^>]*)?>#i', $html, $m);
}

global $wpdb;
$GLOBALS['FAKE_IS_ADMIN'] = true;
$table = $wpdb->prefix . 'eamber_form_leads';

/* --- 1. 反響が0件のとき --- */
ob_start(); eaf_leads_page(); $empty = ob_get_clean();
$th = count_cells($empty, 'th');
t('見出しが出る',             $th > 0, true);
t('「まだありません」が出る', strpos($empty, 'まだありません') !== false, true);
preg_match('#colspan="(\d+)"#', $empty, $cs);
t('空行のcolspanが見出しの数と一致', isset($cs[1]) ? (int)$cs[1] : -1, $th);

/* --- 2. 反響が1件あるとき（★ここで落ちていた） --- */
eaf_activate();   /* テーブルを作ってから入れる（本番の有効化と同じ手順） */
$ins = $wpdb->insert($table, array(
    'created_at' => '2026-08-27 18:00:00',
    'name'       => '鈴木 花子',
    'tel'        => '055-111-2222',
    'email'      => 'hanako@example.test',
    'ptype'      => 'business',
    'address'    => '甲府市',
    'details'    => "■ ご検討の工事 : LED化・照明",
));
t('検査用の反響を保存できた', $ins !== false, true);

$fatal = '';
ob_start();
try { eaf_leads_page(); }
catch (Throwable $e) { $fatal = get_class($e) . ': ' . $e->getMessage(); }
$html = ob_get_clean();

t('例外で落ちない', $fatal, '');
t('お名前が出る',   strpos($html, '鈴木 花子') !== false, true);
t('電話が出る',     strpos($html, '055-111-2222') !== false, true);
t('工事内容は正式名で出る', strpos($html, 'その他の問い合わせ・相談') !== false
                            || strpos($html, '店舗・事務所・工場の設備') !== false, true);
t('削除リンクが出る', strpos($html, '削除</a>') !== false, true);

/* データ行の td が、見出しの th と同じ数だけ出ているか */
preg_match('#<tbody>(.*)</tbody>#s', $html, $tb);
$body = isset($tb[1]) ? $tb[1] : '';
t('見出しの数とデータの数が一致', count_cells($body, 'td'), count_cells($html, 'th'));

/* --- 3. 自己診断：検査が空振りしていないこと --- */
t('自己診断: tdを数えられている', count_cells('<td>a</td><td x="1">b</td>', 'td'), 2);
t('自己診断: 例外を捕まえられる', (function () {
    try { printf('%s %s', 'one'); } catch (Throwable $e) { return true; }
    return false;
})(), true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
