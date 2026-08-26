<?php
/**
 * 記事末への自動挿入。
 *
 * ★オンにすると全記事の見た目が一度に変わる機能なので、
 *   「出ること」より「出てはいけない場所で出ないこと」を厚く検査する。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/at_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
/* ★設定の保存は毎回すべてのキーを書き直す。お問い合わせページのIDを渡し忘れると
   0 に戻り、「遷移先が無いから出さない」規則に引っかかって検査が空振りする。 */
function set_opts($a) {
    $a['form_page_id'] = $GLOBALS['FORM_PAGE_ID'];
    update_option(EAF_OPT, eaf_sanitize_options($a));
}
/** 本文を the_content に通して、フォームが足されたかを見る */
function ran($body = '<p>記事の本文です。</p>') {
    $out = apply_filters('the_content', $body);
    return array($out, strpos($out, 'class="fhs-wrap') !== false);
}
function reset_ctx() {
    unset($GLOBALS['FAKE_SINGULAR'], $GLOBALS['FAKE_IN_LOOP'],
          $GLOBALS['FAKE_MAIN_QUERY'], $GLOBALS['FAKE_IS_FEED'], $GLOBALS['FAKE_IS_ADMIN']);
}

/* お問い合わせページを用意する（遷移先が無いと、そもそも出さない仕様） */
eaf_activate();
$GLOBALS['FORM_PAGE_ID'] = (int) eaf_opt('form_page_id', 0);
t('検査の前提: 遷移先がある', eaf_form_url() !== '', true);

/* --- 1. 既定はオフ --- */
reset_ctx();
set_opts(array());
list($out, $has) = ran();
t('既定では何も足さない', $has, false);
t('本文をそのまま返す', $out, '<p>記事の本文です。</p>');

/* --- 2. オンにすると本文末に出る --- */
set_opts(array('auto_teaser' => '1'));
list($out, $has) = ran();
t('オンで出る', $has, true);
t('本文の「あと」に足す', strpos($out, '記事の本文です。') < strpos($out, 'class="fhs-wrap'), true);
t('既定は横長ティザー', strpos($out, 'fhs-wrap fhs-design-teaser"') !== false, true);
t('本文は消さずに残す', strpos($out, '<p>記事の本文です。</p>') !== false, true);

/* --- 3. 縦も選べる --- */
set_opts(array('auto_teaser' => '1', 'auto_teaser_design' => 'teaser-v'));
list($out, ) = ran();
t('縦を選べる', strpos($out, 'fhs-wrap fhs-design-teaser-v"') !== false, true);
set_opts(array('auto_teaser' => '1', 'auto_teaser_design' => '<script>alert(1)</script>'));
list($out, ) = ran();
t('不正なデザイン指定は横長に落とす', strpos($out, 'fhs-wrap fhs-design-teaser"') !== false, true);

/* --- 4. 出てはいけない場所 --- */
set_opts(array('auto_teaser' => '1'));

$GLOBALS['FAKE_SINGULAR'] = 'page';
list(, $has) = ran();  t('固定ページには出さない', $has, false);

$GLOBALS['FAKE_SINGULAR'] = '';
list(, $has) = ran();  t('一覧ページには出さない', $has, false);

reset_ctx();
$GLOBALS['FAKE_IN_LOOP'] = false;
list(, $has) = ran();  t('本文ループの外では出さない', $has, false);

reset_ctx();
$GLOBALS['FAKE_MAIN_QUERY'] = false;
list(, $has) = ran();  t('関連記事などの副問い合わせでは出さない', $has, false);

reset_ctx();
$GLOBALS['FAKE_IS_FEED'] = true;
list(, $has) = ran();  t('フィードには出さない', $has, false);

reset_ctx();
$GLOBALS['FAKE_IS_ADMIN'] = true;
list(, $has) = ran();  t('管理画面では出さない', $has, false);

/* --- 5. すでに貼ってある記事には足さない（二重表示の防止） --- */
reset_ctx();
$already = eaf_shortcode(array());                       // 展開済みのフォームが本文にある
$out = apply_filters('the_content', $already);
t('展開済みのフォームがあれば足さない', substr_count($out, 'class="fhs-wrap'), 1);
$out = apply_filters('the_content', '<p>本文</p>[eamber_form]');
t('未展開のショートコードがあっても足さない', strpos($out, 'class="fhs-wrap') !== false, false);

/* --- 6. 遷移先が無ければ出さない（行き止まり防止） --- */
$o = get_option(EAF_OPT, array());
$keep = isset($o['form_page_id']) ? $o['form_page_id'] : 0;
$o['form_page_id'] = 0; $o['auto_teaser'] = '1';
update_option(EAF_OPT, $o);
t('前提: 遷移先が消えた', eaf_form_url(), '');
list(, $has) = ran();
t('★遷移先が無ければ出さない', $has, false);
$o['form_page_id'] = $keep; update_option(EAF_OPT, $o);
list(, $has) = ran();
t('戻せばまた出る', $has, true);

/* --- 7. 自己診断 --- */
t('自己診断: 検査がフォームを見つけられている', ran()[1], true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
