<?php
/** ショートコード属性のスペース抜けを拾えるか（実際に吉村さんが書いた形で検証） */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/glue_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';
$ng = 0;
function t($n,$g,$w){ global $ng; $ok=($g===$w); if(!$ok)$ng++;
  printf("%s %s\n     got=%s want=%s\n", $ok?'OK  ':'NG  ', $n, var_export($g,true), var_export($w,true)); }

/* WordPress のパーサが実際に返す形を再現する。
   [eamber_form design="teaser" url="/satei/"width="640"] は
   design は属性として取れ、残りの塊は番号付きで渡ってくる。 */
$atts = array('design' => 'teaser', 0 => 'url="/satei/"width="640"');
$fixed = eaf_unglue_atts($atts);
t('urlを拾えた',   $fixed['url'],   '/satei/');
t('widthを拾えた', $fixed['width'], '640');
t('拾ったフラグ',  $fixed['eaf_glued'], '1');

$html = eaf_shortcode($atts);
t('遷移先が入っている',   strpos($html, "var TARGET  = \"\/satei\/\"") !== false || strpos($html, '"/satei/"') !== false, true);
t('幅が反映されている',   strpos($html, 'max-width:640px') !== false, true);
t('「urlが必要」の赤い警告は出ない', strpos($html, '遷移先が決まっていません') !== false, false);
t('スペース不足の案内が出る',        strpos($html, '半角スペースが足りません') !== false, true);

/* 正しく書いた場合は案内を出さない（誤検知しないこと） */
$ok_html = eaf_shortcode(array('design'=>'teaser','url'=>'/satei/','width'=>'640'));
t('正しい書き方では案内を出さない', strpos($ok_html, '半角スペースが足りません') !== false, false);
t('正しい書き方でも幅は反映',       strpos($ok_html, 'max-width:640px') !== false, true);

/* url を本当に書き忘れた場合は、これまで通り赤い警告 */
$no_url = eaf_shortcode(array('design'=>'teaser'));
t('url未指定なら赤い警告', strpos($no_url, '遷移先が決まっていません') !== false, true);

/* 3つ以上くっついた場合 */
$many = eaf_unglue_atts(array('design'=>'teaser', 0 => 'url="/satei/"width="640"title="テスト"'));
t('3つ連続でも拾える', $many['title'], 'テスト');
echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng?1:0);
