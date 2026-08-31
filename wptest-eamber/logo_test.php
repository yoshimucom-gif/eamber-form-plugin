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

/* --- 電話案内の画像（吹き出し） ---
   ★画像を入れたときだけ吹き出しにする。入れていない環境の見た目を変えないこと。 */
$tel = array('operator_contact' => '090-3451-6042', 'show_telbar' => '1');

update_option(EAF_OPT, eaf_sanitize_options($tel));
$h0 = eaf_shortcode(array());
t('画像なし: 電話案内は出る',       strpos($h0, '<div class="fhs-telbar"') !== false, true);
t('画像なし: 吹き出しにしない',     strpos($h0, 'has-face') !== false, false);
t('画像なし: 画像を出さない',       strpos($h0, 'fhs-telbar-face') !== false, false);
t('画像なし: 中身は今までどおり',   strpos($h0, '090-3451-6042') !== false, true);

update_option(EAF_OPT, eaf_sanitize_options($tel + array('tel_image' => 'https://example.test/staff.jpg')));
$h1 = eaf_shortcode(array());
t('画像あり: 吹き出しに切り替わる', strpos($h1, '<div class="fhs-telbar has-face">') !== false, true);
t('画像あり: 左に画像が出る',
  strpos($h1, '<img class="fhs-telbar-face" src="https://example.test/staff.jpg"') !== false, true);
t('画像は本文より前にある',
  strpos($h1, 'fhs-telbar-face') < strpos($h1, 'fhs-telbar-body'), true);
/* 飾りなので読み上げでは無視させる（本文が二重に読まれないように） */
t('altは空にしてある', strpos($h1, 'class="fhs-telbar-face" src="https://example.test/staff.jpg" alt=""') !== false, true);

$css = fake_inline_style();
t('吹き出しの角がCSSにある', strpos($css, '.fhs-telbar.has-face .fhs-telbar-body::before') !== false, true);
t('画像は丸く切り抜く',     strpos($css, '.fhs-wrap .fhs-telbar-face{') !== false
                            && strpos($css, 'border-radius:50%;object-fit:cover') !== false, true);
t('吹き出しのときは外枠を外す',
  strpos($css, '.fhs-telbar.has-face{background:transparent;border:0') !== false, true);

/* 危険なURLは画像でも保存しない */
update_option(EAF_OPT, eaf_sanitize_options($tel + array('tel_image' => 'javascript:alert(1)')));
t('危険なURLは画像にも入らない', eaf_opt('tel_image', ''), '');

/* 設定画面に欄がある（メディアから選べる形） */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $sp = ob_get_clean();
t('設定画面に電話案内の画像欄がある', strpos($sp, '[tel_image]') !== false, true);
t('丸いプレビューで見せる',           strpos($sp, 'fhs-logo-preview is-round') !== false
                                      || strpos($sp, 'is-round') !== false, true);
$GLOBALS['FAKE_IS_ADMIN'] = false;

/* 自己診断：検査が空振りしていないこと */
t('自己診断: 電話案内そのものを描けている', strpos($h1, 'fhs-telbar-num') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng?1:0);
