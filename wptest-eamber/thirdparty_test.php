<?php
/**
 * 第三者提供（提携会社へ渡す場合）の表示。
 * ・提供先の説明が利用目的と同意文の両方に出ること
 * ・提供先のページURLがあればリンクになること
 * ・OFFのときは「第三者に提供しない」と出ること
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/tp_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }

/* --- 提供する場合：説明とリンク --- */
set_opts(array(
    'third_party' => '1',
    'third_party_name' => '当社が提携する不動産会社（お住まいの地域を担当する1〜3社）',
    'third_party_url'  => 'https://example.test/partners/',
    'privacy_url' => 'https://example.test/privacy',
));
$html = eaf_shortcode(array());
t('提供先の説明が出る',        strpos($html, '当社が提携する不動産会社（お住まいの地域を担当する1〜3社）') !== false, true);
t('提供先がリンクになる',      strpos($html, 'href="https://example.test/partners/"') !== false, true);
t('別タブで開く',              strpos($html, 'target="_blank" rel="noopener"') !== false, true);
t('提供先での取り扱いに触れる', strpos($html, '提供先での取り扱いは、提供先の定めによります') !== false, true);
t('同意文にも提供先が入る',    substr_count($html, 'https://example.test/partners/') >= 2, true);
t('カギ側のポリシーも出る',    strpos($html, 'https://example.test/privacy') !== false, true);

/* --- URLが無ければリンクにしない（ただの文字） --- */
set_opts(array('third_party' => '1', 'third_party_name' => '提携する不動産会社', 'third_party_url' => ''));
$h2 = eaf_shortcode(array('design' => 'card'));
t('URL未設定なら説明は出る',   strpos($h2, '提携する不動産会社') !== false, true);
t('URL未設定ならリンクなし',   preg_match('/<a[^>]*>提携する不動産会社<\/a>/u', $h2) === 1, false);

/* --- 提供しない設定なら、その旨を出す --- */
set_opts(array('third_party' => '0'));
$h3 = eaf_shortcode(array('design' => 'compact'));
t('提供しないと明記される',   strpos($h3, 'ご本人の同意なく第三者に提供することはありません') !== false, true);
t('提供先の話は出ない',       strpos($h3, '提供先での取り扱い') !== false, false);

/* --- 危険なURLは弾く --- */
set_opts(array('third_party' => '1', 'third_party_name' => '提携先', 'third_party_url' => 'javascript:alert(1)'));
t('javascript: は保存されない', eaf_opt('third_party_url', 'EMPTY'), 'EMPTY');

/* --- 自己診断 --- */
t('自己診断: 検査が空振りしていない', strpos(eaf_shortcode(array()), '個人情報の取り扱いについて') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
