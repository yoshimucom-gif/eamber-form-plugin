<?php
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/logo_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';
$ng = 0;
function t($n,$g,$w){ global $ng; $ok=($g===$w); if(!$ok)$ng++; printf("%s %s (got=%s)\n", $ok?'OK  ':'NG  ', $n, var_export($g,true)); }
update_option(EAF_OPT, eaf_sanitize_options(array('logo_url'=>'https://example.test/logo.png')));
t('URLを保存できる', eaf_opt('logo_url'), 'https://example.test/logo.png');
update_option(EAF_OPT, eaf_sanitize_options(array('logo_url'=>'')));
t('空にできる', eaf_opt('logo_url', 'EMPTY'), 'EMPTY');
foreach (array('javascript:alert(1)', 'data:text/html;base64,PHN2Zz4=', 'vbscript:msgbox(1)') as $bad) {
    update_option(EAF_OPT, eaf_sanitize_options(array('logo_url' => $bad)));
    t('危険なURLを保存しない: ' . substr($bad, 0, 16), eaf_opt('logo_url', ''), '');
    $h = eaf_shortcode(array('design'=>'teaser','url'=>'/satei/'));
    t('  出力にも出ない', strpos($h, 'fhs-ticon" src') !== false, false);
}
// 自己診断：この検査が効いているか（正常なURLならちゃんと通ること）
update_option(EAF_OPT, eaf_sanitize_options(array('logo_url' => 'https://example.test/a.png')));
t('自己診断: 正常なURLは通る', eaf_opt('logo_url', ''), 'https://example.test/a.png');
update_option(EAF_OPT, eaf_sanitize_options(array('logo_url'=>'/wp-content/uploads/logo.svg')));
$html = eaf_shortcode(array('design'=>'teaser','url'=>'/satei/'));
t('相対パスも使える', strpos($html, 'src="/wp-content/uploads/logo.svg"') !== false, true);
t('altに運営者名が入る（未設定なら空）', preg_match('/<img class="fhs-ticon"[^>]*alt="[^"]*"/', $html) === 1, true);
echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng?1:0);
