<?php
/**
 * 1ページにティザーを複数置いたときの検証（LPを想定）。
 * ・uid が全部ユニークか（重なると label が他フォームの入力を指す）
 * ・CSS/JS がページに1回だけか
 * ・各フォームが独立した data 属性を持つか
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/multi_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';
$ng = 0;
function t($n,$g,$w){ global $ng; $ok=($g===$w); if(!$ok)$ng++; printf("%s %s (got=%s)\n", $ok?'OK  ':'NG  ', $n, var_export($g,true)); }

$page = '';
for ($i = 0; $i < 8; $i++) {
    $page .= eaf_shortcode(array('design' => 'teaser', 'url' => '/satei/', 'width' => '820'));
}
$page .= eaf_shortcode(array('design' => 'teaser-v', 'url' => '/satei/'));
$page .= eaf_shortcode(array());   // 本フォームも同居

preg_match_all('/<div class="fhs-wrap [^"]*" id="(fhs-[^"]+)"/', $page, $m);
$ids = $m[1];
t('フォーム数', count($ids), 10);
t('uidが全部ユニーク', count(array_unique($ids)), count($ids));

preg_match_all('/ id="([^"]+)"/', $page, $m2);
$allIds = $m2[1];
t('ページ全体のidに重複がない', count(array_unique($allIds)), count($allIds));

/* ★CSS/JSは本文に埋め込まず、WordPressのキューに載せる。
     以前は「最初のショートコードだけが出す」作りで、抜粋やOGP生成で
     先に1回走ると読者に見えるフォームが素のHTMLになっていた（記事で実際に崩れた）。 */
t('本文にstyleを埋め込まない',  substr_count($page, '<style>'), 0);
t('本文にscriptを埋め込まない', substr_count($page, '<script>'), 0);
t('CSSはキューに1回だけ積まれる',
  substr_count(fake_inline_style(), '.fhs-tiles{display:grid'), 1);
t('JSもキューに1回だけ',
  substr_count(fake_inline_script(), 'window.fhsFormsReady = true'), 1);
t('ハンドルが積まれている', wp_style_is('eamber-form', 'enqueued') && wp_script_is('eamber-form', 'enqueued'), true);
/* ★本命の退行検査：本文以外で先に1回描画されても、後の描画でCSSが欠けないこと */
fake_assets_reset();
$discarded = eaf_shortcode(array());          // 抜粋などで捨てられる描画
$visible   = eaf_shortcode(array());          // 読者に見える描画
t('先に捨てられる描画があってもCSSは残る',
  strpos(fake_inline_style(), '.fhs-tiles{display:grid') !== false, true);
t('formは数だけある',    substr_count($page, '<form class="fhs-form"'), 10);

// 各ラッパが自分の設定をdata属性で持っている
t('ティザー印が8+1個', substr_count($page, 'data-fhs-teaser="1"'), 9);
t('遷移先が入っている', substr_count($page, 'data-fhs-target="/satei/"'), 9);

// ラベルのfor が、同じラッパ内のidを指しているか（文字列レベルで確認）
preg_match_all('/<div class="fhs-wrap.*?(?=<div class="fhs-wrap|$)/s', $page, $blocks);
$bad = 0;
foreach ($blocks[0] as $b) {
    preg_match_all('/<label[^>]*for="([^"]+)"/', $b, $fors);
    foreach ($fors[1] as $for) {
        if (strpos($b, 'id="' . $for . '"') === false) $bad++;
    }
}
t('ラベルが自分のフォーム内を指している（外れ数）', $bad, 0);

// 自己診断：検査が効いているか（idを意図的に重複させたら気づけるか）
$broken = str_replace($ids[1], $ids[0], $page);
preg_match_all('/<div class="fhs-wrap [^"]*" id="(fhs-[^"]+)"/', $broken, $m3);
t('自己診断: 重複を作れば検出できる', count(array_unique($m3[1])) < count($m3[1]), true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng?1:0);
