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

/* データ行の td が、見出しの th と同じ数だけ出ているか。
   ★押したときに開く行は別に数える（見出しとは列数が違うのが正しい）。 */
preg_match('#<tbody>(.*)</tbody>#s', $html, $tb);
$body = isset($tb[1]) ? $tb[1] : '';
$main1 = preg_replace('#<tr class="eaf-det-row".*?</tr>#s', '', $body);
t('見出しの数とデータの数が一致', count_cells($main1, 'td'), count_cells($html, 'th'));

/* --- 3. お客様が書いた「ご相談内容」を、押したら読めるか --- */
/* ★ ここが本番で抜けていた。ご相談内容は detail に入るが、
     一覧は details しか出していなかったため、いちばん読みたい文章が消えていた。
     ただし一覧にそのまま並べると、営業メールが1通入っただけで画面が流れる。
     一覧では「詳細あり／なし」だけを見せ、中身は押したときに開く。
     文面は 2026-09-03 に実際に届いた反響から。 */
$soudan = "山梨県甲府市在住のものですが、エアコンが経年劣化もあるのか効かなくなったため、"
        . "交換工事をお願いするとして、お見積もりはどのようになりますでしょうか？\n\n"
        . "リビングの交換で、交換機種は18畳タイプで考えてます。\n"
        . "よろしくお願い致します。";
$wpdb->insert($table, array(
    'created_at' => '2026-09-03 09:33:00',
    'name'       => '保坪 春美',
    'tel'        => '090-0000-0000',
    'ptype'      => 'aircon',
    'address'    => '甲府市',
    'detail'     => $soudan,
    'details'    => "■ ご希望の作業 : 入れ替えたい",
));
ob_start(); eaf_leads_page(); $h2 = ob_get_clean();

t('詳細がある反響には「見る」ボタンが出る',
  strpos($h2, '>見る</button>') !== false, true);
/* ★'eaf-det-btn' は開け閉ての仕掛けの中にも書いてあるので、
     そのまま数えると1つ多くなる。ボタンの印だけを数える。 */
t('詳細がある2件にボタンが出ている',
  substr_count($h2, 'class="button button-small eaf-det-btn"'), 2);
t('中身は最初閉じている',
  strpos($h2, 'class="eaf-det-row" style="display:none"') !== false, true);
t('ご相談内容の本文が入っている',
  strpos($h2, '交換工事をお願いするとして') !== false, true);
t('途中で切られていない',
  strpos($h2, 'よろしくお願い致します') !== false, true);
t('「ご相談内容」の見出しが付く',
  strpos($h2, 'ご相談内容</span>') !== false, true);
t('選択式の回答も一緒に入る',
  strpos($h2, 'ご希望の作業 : 入れ替えたい') !== false, true);
t('開け閉ての仕掛けが入っている',
  strpos($h2, "closest('.eaf-det-btn')") !== false, true);

/* ★ 閉じている行を除いた本体行だけで、見出しと列数が揃っていること。
     printf の %s と引数がずれると PHP 8 では即座に落ちる。 */
preg_match('#<tbody>(.*)</tbody>#s', $h2, $tb2);
$body2 = isset($tb2[1]) ? $tb2[1] : '';
$main  = preg_replace('#<tr class="eaf-det-row".*?</tr>#s', '', $body2);
t('本体行の列数が見出しと揃う（2件分）',
  count_cells($main, 'td'), count_cells($h2, 'th') * 2);
t('開く行は見出しと同じ幅で広がる',
  strpos($h2, 'colspan="' . count_cells($h2, 'th') . '"') !== false, true);

/* ★詳細が何も無い反響。ここにボタンが出ると、
     押しても何も開かないボタンになる。 */
$wpdb->insert($table, array(
    'created_at' => '2026-09-05 10:00:00',
    'name'       => '無記入 太郎',
    'tel'        => '055-999-9999',
    'ptype'      => 'aircon',
    'address'    => '南アルプス市',
));
ob_start(); eaf_leads_page(); $h3 = ob_get_clean();
t('詳細が無い反響にはボタンが出ない',
  substr_count($h3, 'class="button button-small eaf-det-btn"'), 2);
t('詳細が無い行は「-」になる',
  strpos($h3, '<span style="color:#8c8f94">-</span>') !== false, true);
t('開く行は2つのまま',
  substr_count($h3, 'class="eaf-det-row"'), 2);
t('3件目も例外で落ちない', strpos($h3, '無記入 太郎') !== false, true);

/* --- 4. 自己診断：検査が空振りしていないこと --- */
t('自己診断: tdを数えられている', count_cells('<td>a</td><td x="1">b</td>', 'td'), 2);
t('自己診断: 例外を捕まえられる', (function () {
    try { printf('%s %s', 'one'); } catch (Throwable $e) { return true; }
    return false;
})(), true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
