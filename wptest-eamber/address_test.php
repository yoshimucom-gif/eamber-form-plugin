<?php
/**
 * 市町村欄（基幹項目 address）の検査。
 *
 * 旧・本気査定では自由入力の「物件の住所」だったが、eamber-form では
 * 山梨県27市町村＋県外の選択式に変えた。番地を書かせないのは、
 * 訪問可否の判断に必要なのが市町村だけで、詳しい場所は折り返しの電話で
 * 聞けるため（入力量が増えるほど離脱する）。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/addr_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

/* --- 1. 選択肢の定義 --- */
$cities = eaf_opt_list('city');
t('27市町村＋県外の28択', count($cities), 28);
t('先頭は甲府市',         $cities[0], '甲府市');
t('県外の受け皿がある',   in_array('山梨県外', $cities, true), true);
t('重複がない',           count($cities), count(array_unique($cities)));

/* --- 2. 本フォームでは必須のセレクトとして出る --- */
$html = eaf_shortcode(array());
t('セレクトで出る（自由入力ではない）', strpos($html, '<select name="address"') !== false, true);
t('テキスト入力の残骸が無い',           strpos($html, '<input type="text" name="address"') !== false, false);
t('必須になっている',                   preg_match('/お住まい・現場の市町村<span class="fhs-req">必須<\/span>/', $html) === 1, true);
foreach (array('甲府市', '丹波山村', '山梨県外') as $c) {
    t('選択肢: ' . $c, strpos($html, '>' . $c . '</option>') !== false, true);
}
/* 補足文は既定では出さない。書きたいことがあるときだけ設定で出す（出し分け）。
   ★fhs-hint は完了画面のテンプレート（JS内）にも出るので、
     市町村欄から次のラベルまでの区間だけを切り出して見る。 */
function city_block($html) {
    $i = strpos($html, 'name="address"');
    if ($i === false) return '';
    $seg = substr($html, $i);
    $j = strpos($seg, '<label');
    return $j === false ? $seg : substr($seg, 0, $j);
}
t('既定では補足文を出さない', strpos(city_block($html), 'fhs-hint') !== false, false);
update_option(EAF_OPT, eaf_sanitize_options(array('city_hint' => '番地までは不要です', 'show_city_hint' => '1')));
$h2 = eaf_shortcode(array());
t('設定すれば補足文が出る',   strpos(city_block($h2), '番地までは不要です') !== false, true);
update_option(EAF_OPT, eaf_sanitize_options(array()));

/* --- 3. ティザーでもセレクトで出て、本フォームの address 欄へ引き継がれる --- */
$teaser = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/', 'fields' => 'ptype,address'));
t('ティザーでもセレクト', preg_match('/<select name="address"/', $teaser) === 1, true);
t('引き継ぎ名は address（situation_ を付けない）', eaf_teaser_form_name('address'), 'address');

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
