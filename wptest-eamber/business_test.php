<?php
/**
 * 法人の枝（タイル「店舗・事務所・工場」→ 二段目のカード選択＋会社名）。
 *
 * 設計の要点：
 *  ・分岐は「最初に法人か個人か聞く」のではなく、タイルを押したあとに置く。
 *    記事286本のうち法人向けは9本しかないので、最初に仕分けを挟むと
 *    97%の訪問者が自分に関係ない1タップを払うことになる。
 *  ・カードの値は隠し入力に写す。必須チェックも「あと◯項目」も
 *    data-req の付いた入力欄を見て動くため、ラジオのままだと素通りする。
 *  ・会社名は法人＝必須／その他＝任意。個人の8枚には出さない。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/biz_state.json';
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
/** 工事内容ごとの入力欄グループを1つだけ切り出す（CSSや他の枝を巻き込まない）。
    ★会社名は2枚目側の同名グループにあるので、どのSTEPを見るかを指定する。 */
function group_of($html, $pt, $step = 1) {
    $re = '#<div class="fhs-group" data-ptype="' . preg_quote($pt, '#') . '".*?'
        . '(?=<div class="fhs-group"|</form>|<div class="fhs-section")#s';
    $seg = step_of($html, $step);
    return preg_match($re, $seg, $m) ? $m[0] : '';
}

/* ★eaf_sanitize_options は渡さなかったチェック項目を全部オフにする。
     step_form を書かないと2ステップ表示が消え、STEPを見る検査が空振りする。 */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1',
    'step_form' => '1',
)));
$html = eaf_shortcode(array());
t('自己診断: 2ステップで描けている', strpos($html, 'data-step="2"') !== false, true);
$biz  = group_of($html, 'business');
$oth  = group_of($html, 'other');
$air  = group_of($html, 'aircon');
/* 2枚目に出る側（会社名） */
$biz2 = group_of($html, 'business', 2);
$oth2 = group_of($html, 'other', 2);
$air2 = group_of($html, 'aircon', 2);

echo "--- 1. 二段目のカードが法人の枝にだけ出る ---\n";
t('自己診断: 法人の枝を切り出せている', $biz !== '', true);
t('カードのラジオが6枚',      substr_count($biz, 'class="fhs-choice-in"'), 6);
t('カードの見た目も6枚',      substr_count($biz, 'class="fhs-choice-opt"'), 6);
t('セレクトに戻っていない',   strpos($biz, '<select name="business__bz_work"') !== false, false);
t('個人の枝にカードは無い',   strpos($air, 'fhs-choice-in') !== false, false);
t('タイルのアイコンは二段目に付けない', strpos($biz, 'fhs-tile-ico') !== false, false);

echo "\n--- 2. カードの中身が実際のメニューと合っている ---\n";
foreach (array('業務用エアコン', 'LED化・照明', 'キュービクル・受電設備',
               'LAN・電話配線', '新築・改装・原状回復', 'その他・分からない') as $o) {
    t('選択肢「' . $o . '」がある', strpos($biz, 'value="' . $o . '"') !== false, true);
}

echo "\n--- 3. 選ばないと次へ進めない仕掛け ---\n";
t('値を持つ隠し入力がある',   strpos($biz, 'name="business__bz_work" ') !== false
                              || strpos($biz, 'name="business__bz_work"') !== false, true);
t('隠し入力が必須になっている',
  preg_match('/name="business__bz_work"[^>]*data-req="1"/', $biz) === 1, true);
t('ラジオは隠し入力を指している',
  preg_match('/class="fhs-choice-in" data-for="[^"]+"/', $biz) === 1, true);

echo "\n--- 4. 会社名の出しかた（★２枚目に置く） ---\n";
/* ★１枚目はタイル＋カードだけで縦に長い。
   会社名は「何に困っているか」ではなく連絡先の話なので２枚目へ送る。 */
t('★１枚目に会社名は出ない', strpos(step_of($html, 1), 'business__company') !== false, false);
t('★２枚目に会社名が出る', strpos(step_of($html, 2), 'business__company') !== false, true);
t('会社名はお名前より前にある',
  strpos(step_of($html, 2), 'business__company') < strpos(step_of($html, 2), 'customer_name'), true);
t('法人は会社名が必須',
  preg_match('/会社名・屋号<span class="fhs-req">必須<\/span>/', $biz2) === 1, true);
t('その他は会社名が任意',
  preg_match('/会社名・屋号<span class="fhs-opt">任意<\/span>/', $oth2) === 1, true);
t('個人の枝に会社名は出ない', strpos($air . $air2, 'company') !== false, false);
/* 送信キーは変えない（１枚目にあった頃と同じ名前で受ける） */
t('送信キーは business__company のまま',
  strpos($html, 'name="business__company"') !== false, true);
t('建物の用途は既定で出さない', strpos($biz, 'business__bz_kind') !== false, false);

echo "\n--- 5. 保存先（会社名は専用カラムを持つ） ---\n";
$cols = eaf_lead_columns();
t('company カラムが定義に入っている', isset($cols['company']), true);
t('長さは150', isset($cols['company']) ? $cols['company'] : 0, 150);

echo "\n--- 6. 送信の通し（子プロセス） ---\n";
eaf_activate();
$php = PHP_BINARY; $state = $GLOBALS['FAKE_STATE_FILE'];
function submit($post) {
    global $php, $state;
    $f = __DIR__ . '/biz_post.json';
    file_put_contents($f, json_encode($post, JSON_UNESCAPED_UNICODE));
    $out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/submit_case.php')
                    . ' ' . escapeshellarg($f) . ' ' . escapeshellarg($state) . ' 2>&1');
    @unlink($f);
    unset($GLOBALS['FAKE_STATE']);
    return json_decode((string)$out, true);
}
function last_row() {
    $s = fake_state();
    $r = isset($s['rows']['wp_eamber_form_leads']) ? $s['rows']['wp_eamber_form_leads'] : array();
    return $r ? $r[count($r) - 1] : array();
}

$biz_post = array(
    'ptype' => 'business', 'address' => '甲府市', 'address_detail' => '丸の内1-2-3', 'agree' => '1',
    'business__bz_work' => 'キュービクル・受電設備',
    'business__company' => '株式会社サンプル電機',
    'customer_name' => '田中 一郎', 'customer_tel' => '090-1234-5678', 'email' => '',
);
$r = submit($biz_post);
t('法人の反響を受理する', is_array($r) && $r['ok'] === true, true);
$row = last_row();
t('会社名が専用カラムに入る', isset($row['company']) ? $row['company'] : '', '株式会社サンプル電機');
t('カードの選択が詳細に残る',
  isset($row['details']) && strpos($row['details'], 'キュービクル・受電設備') !== false, true);
/* ★列と詳細の両方に同じ社名が並ぶと、CSVでもシートでも二重に見える */
t('会社名は詳細に二重掲載しない',
  isset($row['details']) && strpos($row['details'], '会社名') !== false, false);
$s = fake_state();
$notify = $s['mails'][count($s['mails']) - 1]['body'];
t('通知メールには会社名が入る', strpos($notify, '株式会社サンプル電機') !== false, true);

echo "\n--- 7. 未入力・改ざん ---\n";
$r = submit(array_merge($biz_post, array('business__company' => '', 'customer_tel' => '090-2222-3333')));
t('法人で会社名が空なら弾く',
  is_array($r) && !empty($r['errors']) && strpos(implode('', $r['errors']), '会社名') !== false, true);
$r = submit(array_merge($biz_post, array('business__bz_work' => '架空の工事', 'customer_tel' => '090-3333-4444')));
t('選択肢外の値は捨てて必須エラーにする',
  is_array($r) && !empty($r['errors']) && strpos(implode('', $r['errors']), 'ご検討の工事') !== false, true);

echo "\n--- 8. その他の枝は会社名なしでも通る ---\n";
$r = submit(array(
    'ptype' => 'other', 'address' => '甲府市', 'address_detail' => '丸の内1-2-3', 'agree' => '1',
    'other__ot_note' => '取引のご相談です', 'other__company' => '',
    'customer_name' => '佐藤 花子', 'customer_tel' => '090-5555-6666', 'email' => '',
));
t('会社名なしでも受理する', is_array($r) && $r['ok'] === true, true);
t('会社名は空のまま保存', last_row()['company'], '');

echo "\n--- 9. スプレッドシートにも会社名を送る ---\n";
$pay = eaf_sheet_payload(array('company' => '株式会社サンプル電機', 'ptype' => 'business'));
t('転記の中身に会社名がある', isset($pay['company']) ? $pay['company'] : '', '株式会社サンプル電機');
$gas = eaf_sheet_gas_code();
t('スクリプトの見出しに会社名がある', strpos($gas, "'会社名'") !== false, true);
t('スクリプトが会社名を書き込む',     strpos($gas, 'data.company') !== false, true);

echo "\n--- 10. 自己診断（検査が空振りしていないこと） ---\n";
t('自己診断: 枝の切り出しは他の枝を巻き込まない', strpos($air, 'business__') !== false, false);
t('自己診断: 存在しない選択肢は見つからない', strpos($biz, 'value="架空の工事"') !== false, false);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
