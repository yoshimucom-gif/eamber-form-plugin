<?php
/**
 * 現場の住所（市町村セレクト ＋ 丁目・番地・建物名）の検査。
 *
 * ・市町村は山梨県27市町村＋県外の選択式（自由入力にしない。表記ゆれが出て
 *   対応エリアの判定ができなくなるため）。
 * ・その下に丁目・番地・建物名を受け取る。市町村だけでは現地の下見も
 *   見積りも組めない。★以前は「番地までは不要」の運用だったので、
 *   その名残の文言が残っていないかもここで見る。
 * ・住所はSTEP2に置く。1画面目は「何に困っているか」だけにしておき、
 *   住所のような個人情報は、進む意思を示したあとに聞く。
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
/** STEPの箱を1つ切り出す */
function step_of($html, $n) {
    $re = '#<div class="fhs-formstep" data-step="' . (int) $n . '".*?(?=<div class="fhs-formstep"|<div class="fhs-nav">)#s';
    return preg_match($re, $html, $m) ? $m[0] : '';
}

/* --- 1. 選択肢の定義 --- */
$cities = eaf_opt_list('city');
t('27市町村＋県外の28択', count($cities), 28);
t('先頭は甲府市',         $cities[0], '甲府市');
t('県外の受け皿がある',   in_array('山梨県外', $cities, true), true);
t('重複がない',           count($cities), count(array_unique($cities)));

/* --- 2. 住所はSTEP2にある --- */
$html = eaf_shortcode(array());
$s1 = step_of($html, 1);
$s2 = step_of($html, 2);
t('自己診断: STEPを切り出せている', $s1 !== '' && $s2 !== '', true);
t('★STEP1に市町村は出ない',   strpos($s1, 'name="address"') !== false, false);
t('★STEP1に番地欄も出ない',   strpos($s1, 'name="address_detail"') !== false, false);
t('STEP2に市町村がある',       strpos($s2, '<select name="address"') !== false, true);
t('STEP2に番地欄がある',       strpos($s2, 'name="address_detail"') !== false, true);
t('進捗の名前も住所を含む',
  strpos(fake_inline_script(), 'ご連絡先・住所') !== false, true);

/* --- 3. 市町村はセレクトで必須 --- */
t('セレクトで出る（自由入力ではない）', strpos($html, '<select name="address"') !== false, true);
t('テキスト入力の残骸が無い',           strpos($html, '<input type="text" name="address"') !== false, false);
t('必須になっている', preg_match('/現場の住所<span class="fhs-req">必須<\/span>/', $html) === 1, true);
foreach (array('甲府市', '丹波山村', '山梨県外') as $c) {
    t('選択肢: ' . $c, strpos($html, '>' . $c . '</option>') !== false, true);
}

/* --- 4. 見た目はひと続きの1欄。ラベルは2つに割らない ---
   ★「お住まい・現場の市町村」と「丁目・番地・建物名」を別々の見出しにすると、
     同じ住所を二度聞かれているように見える。中身は分けたまま、見せ方だけ1つにする。 */
t('見出しは1つだけ', substr_count($html, '>現場の住所<span'), 1);
t('市町村だけの見出しは残っていない', strpos($html, 'お住まい・現場の市町村') !== false, false);
t('丁目・番地の見出しを別に立てない',
  preg_match('/丁目・番地・建物名<span class="fhs-(req|opt)">/', $html) === 1, false);
/* 同じ囲みの中に、市町村セレクトと番地入力が並ぶ */
$box = preg_match('#<div class="fhs-addr">.*?</div>#s', $s2, $m) ? $m[0] : '';
t('自己診断: 住所の囲みを切り出せている', $box !== '', true);
t('囲みの中に市町村がある', strpos($box, '<select name="address"') !== false, true);
t('囲みの中に番地がある',   strpos($box, 'name="address_detail"') !== false, true);
/* 見出しを共有するので、番地には読み上げ用の名前を付ける */
t('番地に読み上げ用の名前がある',
  strpos($box, 'aria-label="丁目・番地・建物名"') !== false, true);
t('書き方の例が入っている', strpos($html, '丸の内1-2-3') !== false, true);
t('番地は必須として扱う', preg_match('/name="address_detail"[^>]*data-req="1"/', $box) === 1, true);

/* --- 5. 設定で任意・非表示にできる --- */
update_option(EAF_OPT, eaf_sanitize_options(array('mode_address_address_detail' => 'opt')));
$h_opt = eaf_shortcode(array());
t('任意にすると必須の印が外れる',
  preg_match('/name="address_detail"[^>]*data-req="1"/', $h_opt) === 1, false);
t('任意だと分かる書き方に変わる', strpos($h_opt, '丁目・番地・建物名（任意）') !== false, true);
update_option(EAF_OPT, eaf_sanitize_options(array('mode_address_address_detail' => 'off')));
$h_off = eaf_shortcode(array());
t('非表示にできる', strpos($h_off, 'name="address_detail"') !== false, false);
t('番地を出さないときは見出しが市町村に戻る',
  strpos($h_off, 'お住まい・現場の市町村') !== false, true);
update_option(EAF_OPT, eaf_sanitize_options(array()));

/* --- 6. 補足文（出し分け） --- */
function city_block($html) {
    $i = strpos($html, 'name="address"');
    if ($i === false) return '';
    $seg = substr($html, $i);
    $j = strpos($seg, 'メールアドレス');
    return $j === false ? $seg : substr($seg, 0, $j);
}
t('既定では補足文を出さない', strpos(city_block($html), 'fhs-hint') !== false, false);
update_option(EAF_OPT, eaf_sanitize_options(array(
    'city_hint' => 'お伺いする住所として使います', 'show_city_hint' => '1')));
t('設定すれば補足文が出る',
  strpos(city_block(eaf_shortcode(array())), 'お伺いする住所として使います') !== false, true);

/* ★「番地までは不要」の名残は、番地を受け取る今は内容が逆になる。
     保存されていたら一度だけ捨てる（手で書いた別の文言は残す）。 */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'city_hint' => '番地までは不要です。詳しい場所は折り返しの際にうかがいます', 'show_city_hint' => '1')));
delete_option('eaf_field_defaults_ver');
eaf_maybe_reset_field_modes();
t('★「番地までは不要」の名残を捨てる', eaf_opt('city_hint', ''), '');
update_option(EAF_OPT, eaf_sanitize_options(array()));

/* --- 7. 保存先 --- */
$cols = eaf_lead_columns();
t('address_detail カラムがある', isset($cols['address_detail']), true);
t('長さは255', isset($cols['address_detail']) ? $cols['address_detail'] : 0, 255);

/* --- 8. 送信すると住所がひと続きで届く --- */
eaf_activate();
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1')));
$php = PHP_BINARY; $state = $GLOBALS['FAKE_STATE_FILE'];
$f = __DIR__ . '/addr_post.json';
file_put_contents($f, json_encode(array(
    'ptype' => 'aircon', 'address' => '甲府市', 'address_detail' => '丸の内1-2-3 ○○マンション101',
    'agree' => '1', 'aircon__ac_work' => '入れ替えたい',
    'customer_name' => '山田 太郎', 'customer_tel' => '090-1234-5678', 'email' => '',
), JSON_UNESCAPED_UNICODE));
$out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/submit_case.php')
                . ' ' . escapeshellarg($f) . ' ' . escapeshellarg($state) . ' 2>&1');
@unlink($f);
unset($GLOBALS['FAKE_STATE']);
$res = json_decode((string) $out, true);
t('受理される', is_array($res) && $res['ok'] === true, true);

$st = fake_state();
$rows = $st['rows']['wp_eamber_form_leads'];
$row = $rows[count($rows) - 1];
t('市町村は市町村の列に',   $row['address'], '甲府市');
t('番地は番地の列に',       $row['address_detail'], '丸の内1-2-3 ○○マンション101');
t('番地を詳細に二重掲載しない', strpos((string) $row['details'], '丸の内') !== false, false);

$notify = $st['mails'][count($st['mails']) - 1]['body'];
t('★通知メールは住所がひと続きで出る',
  strpos($notify, '■ 現場の住所 : 甲府市 丸の内1-2-3 ○○マンション101') !== false, true);
t('「現場の市町村」の見出しは残っていない', strpos($notify, '現場の市町村') !== false, false);

/* --- 9. ティザーは市町村だけ（入口なので手数を増やさない） --- */
$teaser = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/', 'fields' => 'ptype,address'));
t('ティザーでもセレクト', preg_match('/<select name="address"/', $teaser) === 1, true);
t('ティザーに番地欄は出さない', strpos($teaser, 'address_detail') !== false, false);
t('引き継ぎ名は address（situation_ を付けない）', eaf_teaser_form_name('address'), 'address');

/* --- 10. 自己診断 --- */
t('自己診断: STEP1を取り違えていない', strpos($s1, 'name="ptype"') !== false, true);
t('自己診断: STEP2を取り違えていない', strpos($s2, 'name="agree"') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
