<?php
/** WordPress本体と同じ属性パーサで、吉村さんが書いた文字列そのものを通して検証する */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/parse_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

// WordPress 6.x の shortcode_parse_atts（wp-includes/shortcodes.php）と同じ実装
function wp_shortcode_parse_atts($text) {
    $atts    = array();
    $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
    $text    = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text);
    if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
        foreach ($match as $m) {
            if (!empty($m[1]))                     $atts[strtolower($m[1])] = stripcslashes($m[2]);
            elseif (!empty($m[3]))                 $atts[strtolower($m[3])] = stripcslashes($m[4]);
            elseif (!empty($m[5]))                 $atts[strtolower($m[5])] = stripcslashes($m[6]);
            elseif (isset($m[7]) && strlen($m[7])) $atts[] = stripcslashes($m[7]);
            elseif (isset($m[8]) && strlen($m[8])) $atts[] = stripcslashes($m[8]);
            elseif (isset($m[9]))                  $atts[] = stripcslashes($m[9]);
        }
        foreach ($atts as &$value) if (false !== strpos($value, '<') && 1 !== preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value)) $value = '';
    }
    return $atts;
}
$ng = 0;
function t($n,$g,$w){ global $ng; $ok=($g===$w); if(!$ok)$ng++;
  printf("%s %s\n     got=%s want=%s\n", $ok?'OK  ':'NG  ', $n, var_export($g,true), var_export($w,true)); }

// ★吉村さんが実際に書いた文字列
$raw = 'design="teaser" url="/satei/"width="640"';
$parsed = wp_shortcode_parse_atts($raw);
echo "WordPressが読み取った結果: " . var_export($parsed, true) . "\n\n";
t('WPは url を属性として読めない（想定どおり）', isset($parsed['url']), false);

$html = eaf_shortcode($parsed);
t('救済して遷移先が入る',   strpos($html, '/satei/') !== false, true);
t('救済して幅も反映される', strpos($html, 'max-width:640px') !== false, true);
t('赤い「urlが必要」は出ない', strpos($html, '遷移先が決まっていません') !== false, false);
t('スペース不足の案内が出る',  strpos($html, '半角スペースが足りません') !== false, true);

// 正しい書き方
$ok = wp_shortcode_parse_atts('design="teaser" url="/satei/" width="640"');
t('正しく書けばWPが素直に読む', $ok['url'], '/satei/');
$okHtml = eaf_shortcode($ok);
t('案内は出ない', strpos($okHtml, '半角スペースが足りません') !== false, false);
t('幅は反映される', strpos($okHtml, 'max-width:640px') !== false, true);

// シングルクォート・複数連結
$sq = wp_shortcode_parse_atts('design="teaser" url="/satei/"width="640"title="テスト見出し"');
$sqHtml = eaf_shortcode($sq);
t('3つ連結でも見出しが反映', strpos($sqHtml, 'テスト見出し') !== false, true);
echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng?1:0);
