<?php
/**
 * 対応会社の画像（会社名・所在地の左に丸く出す）。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/cimg_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }

$base = array('operator_name' => '株式会社e.Amber', 'operator_address' => '山梨県甲府市○○1-2-3');

/* --- 設定すれば出る --- */
set_opts($base + array('company_image' => 'https://example.test/company.png'));
$html = eaf_shortcode(array());
t('画像が出る',             strpos($html, 'class="fhs-opimg" src="https://example.test/company.png"') !== false, true);
t('altに会社名が入る',      strpos($html, 'alt="株式会社e.Amber"') !== false, true);
t('遅延読み込みを付ける',   strpos($html, 'loading="lazy"') !== false, true);
t('画像→情報の順に並ぶ',   strpos($html, 'fhs-opimg') < strpos($html, 'fhs-operator-info'), true);
t('丸くするCSSがある',      strpos($html, '.fhs-wrap .fhs-opimg{width:72px;height:72px') !== false, true);
t('正方形に切り出すCSS',    strpos($html, 'object-fit:cover') !== false, true);

/* --- 未設定なら出ない（会社の欄自体は出る） --- */
set_opts($base);
$h2 = eaf_shortcode(array('design' => 'card'));
t('未設定なら画像は出ない', strpos($h2, 'fhs-opimg') !== false, false);
t('会社の欄は出る',         strpos($h2, '対応会社') !== false, true);

/* --- 危険なURLは弾く --- */
foreach (array('javascript:alert(1)', 'data:text/html;base64,PHN2Zz4=') as $bad) {
    set_opts($base + array('company_image' => $bad));
    t('危険なURLは保存しない: ' . substr($bad, 0, 12), eaf_opt('company_image', ''), '');
}

/* --- ティザーには会社欄ごと出ない --- */
set_opts($base + array('company_image' => 'https://example.test/company.png'));
t('ティザーには出ない', strpos(eaf_shortcode(array('design' => 'teaser', 'url' => '/satei/')), 'fhs-opimg') !== false, false);

/* --- 設定画面に画像選択欄が2つ（見出しアイコン・会社の画像）--- */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('画像を選ぶ欄が2つある',   substr_count($page, 'class="fhs-logofield"'), 2);
t('IDではなくクラスで組む',  strpos($page, 'id="fhs-logo-url"') !== false, false);
t('会社の画像は丸プレビュー', strpos($page, 'fhs-logo-preview is-round') !== false || strpos($page, 'is-empty is-round') !== false, true);

/* --- 自己診断 --- */
t('自己診断: 検査が空振りしていない', strpos(eaf_shortcode(array('design' => 'card')), 'fhs-operator-info') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
