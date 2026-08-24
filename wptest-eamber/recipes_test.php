<?php
/**
 * 設定画面「使い方」に載せているコピペ用ショートコードを全部抜き出し、
 * WordPress本体と同じパーサに通して、実際に意図どおり動くかを確かめる。
 * ★例が間違っていたら意味がないので、例を追加したらこのテストが自動で拾う。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/recipes_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

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
    }
    return $atts;
}

$ng = 0;
function t($n,$g,$w){ global $ng; $ok=($g===$w); if(!$ok)$ng++;
  printf("  %s %s (got=%s)\n", $ok?'OK  ':'NG  ', $n, var_export($g,true)); }

$GLOBALS['FAKE_IS_ADMIN'] = true;
// 実運用と同じ状態にする（有効化時にお問い合わせページが下書きで作られ、ティザーの遷移先が決まる）
eaf_activate();
ob_start(); eaf_settings_page(); $page = ob_get_clean();

/* ★設定画面に出てくる [eamber_form ...] を全部拾う。
   コピーボタン付きの例だけを見ていたら、デザインパターン表の古い例
   （url="/satei/" が残っていた）を見逃した。書いてある例はすべて検査対象にする。 */
preg_match_all('/<code[^>]*>(\[eamber_form[^\]]*\])<\/code>/s', $page, $m);
$codes = array_values(array_unique(array_map(function ($c) {
    return html_entity_decode(trim($c), ENT_QUOTES, 'UTF-8');
}, $m[1])));
printf("使い方タブに載っている例: %d 件\n\n", count($codes));
if (count($codes) < 5) { echo "NG: 例が少なすぎる（抽出できていない可能性）\n"; exit(1); }

/* 説明文の中に古い書き方が残っていないか（例の本体以外も見る） */
$stale = array();
if (preg_match_all('/url="\/satei\/"/', $page, $mm)) $stale[] = 'url="/satei/" が ' . count($mm[0]) . ' 箇所';
if ($stale) { echo 'NG: 古い書き方が残っている: ' . implode(' / ', $stale) . "\n"; $ng++; }
else echo "OK  古い url=\"/satei/\" の記載は残っていない\n\n";

foreach ($codes as $code) {
    $code = html_entity_decode(trim($code), ENT_QUOTES, 'UTF-8');
    echo $code . "\n";
    // [eamber_form ...] から属性部分を取り出す
    if (!preg_match('/^\[eamber_form\s*(.*?)\]$/s', $code, $mm)) { echo "  NG  形が壊れている\n"; $ng++; continue; }
    $atts = wp_shortcode_parse_atts($mm[1]);
    $html = eaf_shortcode($atts);

    t('管理者向けの赤い警告が出ない', strpos($html, 'fhs-admin-warn') !== false && strpos($html, 'fhs-admin-note') === false, false);
    t('スペース抜けの案内が出ない',   strpos($html, '半角スペースが足りません') !== false, false);
    t('PHPエラーが出ていない',        preg_match('/(Warning|Notice|Fatal error|Deprecated):/', $html) === 1, false);

    $isTeaser = isset($atts['design']) && strpos($atts['design'], 'teaser') === 0;
    if ($isTeaser) {
        t('遷移先が入っている', strpos($html, '"\/form\/"') !== false || strpos($html, '"/form/"') !== false, true);
        t('入口フォームとして描画', strpos($html, 'fhs-thead') !== false, true);
        if (isset($atts['width'])) {
            $w = preg_match('/^\d+$/', $atts['width']) ? $atts['width'].'px' : $atts['width'];
            t('幅 ' . $w . ' が反映', strpos($html, 'max-width:' . $w) !== false, true);
        }
        if (isset($atts['title']))    t('見出しが反映',   strpos($html, $atts['title']) !== false, true);
        if (isset($atts['subtitle'])) t('小見出しが反映', strpos($html, $atts['subtitle']) !== false, true);
        if (isset($atts['fields'])) {
            foreach (explode(',', $atts['fields']) as $f) {
                $f = trim($f);
                $nm = eaf_teaser_form_name($f);
                t('項目 ' . $f . ' が出ている', strpos($html, 'name="' . $nm . '"') !== false, true);
            }
        }
    } else {
        t('本フォームとして描画', strpos($html, 'name="email"') !== false && strpos($html, 'name="agree"') !== false, true);
    }
    echo "\n";
}
/* ★このテストが本当に効いているかの自己診断。
   「NG 0件」は“問題なし”と“検査が動いていない”の2通りありうるので、
   わざと壊れた例を通して、ちゃんとNGとして拾えることを確かめる。 */
echo "自己診断（わざと壊れた例を通す）\n";
$selfNg = 0;
$broken = wp_shortcode_parse_atts('design="teaser" url="/contact/"width="640"');   // スペース抜け
$bh = eaf_shortcode($broken);
if (strpos($bh, '半角スペースが足りません') === false) { echo "  NG  スペース抜けを検出できていない\n"; $selfNg++; }
else echo "  OK  スペース抜けを検出できる\n";
/* 遷移先がどこにも無いとき（お問い合わせページ未設定 かつ url 未指定）だけ警告が出ること。
   お問い合わせページが設定されていれば url を書かなくても警告は出ない。 */
$o = get_option(EAF_OPT, array());
$keep = isset($o['form_page_id']) ? $o['form_page_id'] : 0;
$o['form_page_id'] = 0; update_option(EAF_OPT, $o);
$nourl = eaf_shortcode(wp_shortcode_parse_atts('design="teaser"'));
$o['form_page_id'] = $keep; update_option(EAF_OPT, $o);
if (strpos($nourl, '遷移先が決まっていません') === false) { echo "  NG  遷移先なしを検出できていない\n"; $selfNg++; }
else echo "  OK  遷移先なしを検出できる\n";

$withPage = eaf_shortcode(wp_shortcode_parse_atts('design="teaser"'));
if (strpos($withPage, '遷移先が決まっていません') !== false) { echo "  NG  お問い合わせページがあるのに警告が出る\n"; $selfNg++; }
else echo "  OK  お問い合わせページがあれば警告は出ない\n";
$ng += $selfNg;

echo "\n";
echo $ng ? "### 失敗 {$ng} 件\n" : "### 載せている例はすべて正しく動きます\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng?1:0);
