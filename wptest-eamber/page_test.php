<?php
/**
 * お問い合わせページの自動作成と、ティザーの遷移先の既定値を確かめる。
 *
 * ・有効化で form の固定ページを「下書き」で作る
 * ・同じスラッグのページが既にあれば、それを使う（勝手に増やさない）
 * ・一度作ったら、利用者が消しても二度と自動作成しない
 * ・ティザーは url を省略すると、そのお問い合わせページへ送る
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/page_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

/* --- 1. 初回の有効化 --- */
eaf_activate();
$id = (int) eaf_opt('form_page_id', 0);
t('お問い合わせページが作られた', $id > 0, true);
$page = get_page_by_path('form', OBJECT, 'page');
t('スラッグは form',        $page ? $page->post_name : null, 'form');
t('固定ページとして作られる', $page ? $page->post_type : null, 'page');
t('下書きで作られる',        $page ? $page->post_status : null, 'draft');
t('ショートコードが入っている', $page && strpos($page->post_content, '[eamber_form]') !== false, true);
t('Gutenbergのブロック形式',   $page && strpos($page->post_content, '<!-- wp:shortcode -->') !== false, true);
t('作成を知らせるフラグが立つ', get_option('eaf_page_notice'), '1');

/* --- 2. 何度有効化しても増えない --- */
eaf_activate();
eaf_activate();
$s = fake_state();
$pages = 0;
foreach ($s['posts'] as $p) if ($p['post_name'] === 'form') $pages++;
t('再有効化しても増えない', $pages, 1);

/* --- 3. ティザーの遷移先が自動で入る（url 省略） --- */
$html = eaf_shortcode(array('design' => 'teaser'));
t('url を書かなくても遷移先が入る', strpos($html, 'data-fhs-target="/form/"') !== false, true);
t('「遷移先が決まっていません」が出ない', strpos($html, '遷移先が決まっていません') !== false, false);

/* --- 4. ショートコードの url が優先される --- */
$html2 = eaf_shortcode(array('design' => 'teaser', 'url' => '/other/'));
t('url 指定が優先される', strpos($html2, 'data-fhs-target="/other/"') !== false, true);

/* --- 5. 公開すれば下書き警告は消える（状態を見ているか） --- */
t('いまは下書き', get_post_status($id), 'draft');
fake_set_post_status($id, 'publish');
t('公開に変えられる', get_post_status($id), 'publish');
t('公開後も遷移先は同じ', eaf_form_url(), '/form/');

/* --- 6. 利用者がページを消したら、勝手に作り直さない --- */
$posts = fake_state()['posts'];
unset($posts[$id]);
fake_set('posts', $posts);
$o = get_option(EAF_OPT, array()); $o['form_page_id'] = 0; update_option(EAF_OPT, $o);
eaf_activate();
t('消されたら作り直さない', (int) eaf_opt('form_page_id', 0), 0);
t('遷移先は空になる',       eaf_form_url(), '');
$html3 = eaf_shortcode(array('design' => 'teaser'));
t('遷移先が無ければ管理者に知らせる', strpos($html3, '遷移先が決まっていません') !== false, true);

/* --- 7. 同じスラッグのページが先にある場合はそれを使う --- */
@unlink($GLOBALS['FAKE_STATE_FILE']);
unset($GLOBALS['FAKE_STATE']);
$mine = wp_insert_post(array('post_title' => '自分で作ったお問い合わせページ', 'post_name' => 'form',
                             'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[eamber_form]'));
eaf_activate();
t('既存ページを採用する', (int) eaf_opt('form_page_id', 0), (int) $mine);
$s2 = fake_state();
$cnt = 0;
foreach ($s2['posts'] as $p) if ($p['post_name'] === 'form') $cnt++;
t('既存があれば新規作成しない', $cnt, 1);

/* --- 自己診断 --- */
t('自己診断: 存在しないIDは状態falseになる', get_post_status(99999), false);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
