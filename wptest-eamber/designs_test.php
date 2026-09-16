<?php
/**
 * ショートコードで使い分ける3つの型の検査。
 *
 *   design="simple"  旧サイトと同じ形。工事内容を聞かない・1画面
 *   design="select"  工事内容をドロップダウンで選び、選択に応じて次の項目が変わる・1画面
 *   （既定）          タイル13枚＋2ステップ
 *
 * ★描画と受け取りで同じ判断をしているかを、実際に送信して確かめる。
 *   片方だけ型を意識していると「画面に無い項目を入力してください」で送れなくなる。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/designs_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test',
    'notify_on' => '1', 'step_form' => '1',
)));
eaf_activate();

$simple = eaf_shortcode(array('design' => 'simple'));
$select = eaf_shortcode(array('design' => 'select'));
$normal = eaf_shortcode(array());

echo "--- 1. 工事メニューは13種類 ---\n";
$menu = $GLOBALS['EAF_PTYPE_LABEL'];
t('メニューの数', count($menu), 13);
foreach (array(
    'ecocute' => 'エコキュート・電気温水器',
    'solar'   => '太陽光発電',
    'battery' => '蓄電池・EV充電',
    'camera'  => '防犯カメラ',
) as $k => $label) {
    t('追加: ' . $label, isset($menu[$k]) ? $menu[$k] : '', $label);
    t('  タイルの短い名前がある', isset($GLOBALS['EAF_PTYPE_SHORT'][$k]), true);
    t('  代表例がある',           isset($GLOBALS['EAF_PTYPE_NOTE'][$k]), true);
    t('  アイコンがある',         eaf_ptype_icon($k) !== '', true);
    t('  必須の質問が1つある',
      count(eaf_visible_fields('prop_' . $k, eaf_property_fields()[$k], true)), 1);
}
/* ★「その他」は必ず最後。途中に挟むと、ここで終わりだと思われて下を見てもらえない */
$keys = array_keys($menu);
t('「その他」は最後', $keys[count($keys) - 1], 'other');

echo "\n--- 2. simple：工事内容を聞かない ---\n";
t('タイルを出さない',           strpos($simple, 'fhs-tile-input') !== false, false);
t('ドロップダウンも出さない',   strpos($simple, '<select name="ptype"') !== false, false);
t('工事内容は「その他」で記録',
  strpos($simple, '<input type="hidden" name="ptype" value="other">') !== false, true);
t('受け取り側に型を伝える',
  strpos($simple, '<input type="hidden" name="simple" value="1">') !== false, true);
t('枝の質問を出さない',         strpos($simple, 'data-ptype=') !== false, false);
t('ステップに分けない',         strpos($simple, 'fhs-formstep') !== false, false);
t('会社名も出さない',           strpos($simple, 'business__company') !== false, false);

echo "\n--- 3. simple：旧サイトと同じ項目 ---\n";
$names = array();
preg_match_all('/name="([^"]+)"/', $simple, $m);
foreach ($m[1] as $n) { if (!in_array($n, $names, true)) $names[] = $n; }
sort($names);
t('入力欄はこれだけ', $names, array(
    'address', 'address_detail', 'agree', 'compact', 'customer_name', 'customer_tel',
    'eaf_website', 'email', 'ptype', 'simple', 'situation_detail',
));
t('お名前は必須',   preg_match('/name="customer_name"[^>]*data-req="1"/', $simple) === 1, true);
t('電話は必須',     preg_match('/name="customer_tel"[^>]*data-req="1"/', $simple) === 1, true);
t('ご相談内容は必須', preg_match('/name="situation_detail"[^>]*data-req="1"/', $simple) === 1, true);
/* ★旧サイトは住所そのものが任意だった。市町村は対応エリアの判定に要るので必須のまま、
     番地は任意にして手数を増やさない */
t('番地は任意',     preg_match('/name="address_detail"[^>]*data-req="1"/', $simple) === 1, false);
t('メールは任意',   strpos($simple, 'メールアドレス<span class="fhs-opt">任意</span>') !== false, true);
t('同意は残す',     strpos($simple, 'name="agree"') !== false, true);
/* 画像認証は付けない（ハニーポット・経過時間・回数制限が常時効いているため） */
t('画像認証は無い', strpos($simple, 'captcha') !== false, false);

echo "\n--- 4. simple は設定で形が変わらない ---\n";
/* ★「シンプル」を選んだのに設定次第で項目が増減すると、使い分ける意味がなくなる */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test',
    'notify_on' => '1', 'step_form' => '1',
    'mode_situation_detail' => 'off',      // 既定側では消す指定
    'mode_customer_kana'    => 'req',      // 既定側では増やす指定
)));
$s2 = eaf_shortcode(array('design' => 'simple'));
t('ご相談内容は消えない',   strpos($s2, 'name="situation_detail"') !== false, true);
t('フリガナは増えない',     strpos($s2, 'name="customer_kana"') !== false, false);
/* 既定の型のほうにはちゃんと効いている（設定そのものは生きている） */
$n2 = eaf_shortcode(array());
t('自己診断: 既定の型には設定が効く', strpos($n2, 'name="customer_kana"') !== false, true);
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test',
    'notify_on' => '1', 'step_form' => '1',
)));

echo "\n--- 5. select：ドロップダウンで選んで分岐 ---\n";
t('ドロップダウンを出す',   strpos($select, '<select name="ptype"') !== false, true);
t('タイルは出さない',       strpos($select, 'fhs-tile-input') !== false, false);
/* ★工事内容のドロップダウンだけを切り出して数える。
     画面全体で数えると、枝の質問や市町村の選択肢まで混ざる。 */
$ptsel = preg_match('#<select name="ptype".*?</select>#s', $select, $mm) ? $mm[0] : '';
t('自己診断: ドロップダウンを切り出せている', $ptsel !== '', true);
t('選択肢は13種類＋「選択してください」', substr_count($ptsel, '<option value="'), 14);
foreach (array('エアコン（取り付け・修理）', '太陽光発電', 'その他の問い合わせ・相談') as $label) {
    t('選択肢に「' . $label . '」', strpos($select, '>' . $label . '</option>') !== false, true);
}
t('枝の質問は出る（分岐する）', strpos($select, 'data-ptype="solar"') !== false, true);
t('ステップに分けない',         strpos($select, 'fhs-formstep') !== false, false);
t('必須の項目だけ出す',         strpos($select, 'name="customer_kana"') !== false, false);

echo "\n--- 6. 既定の型はこれまでどおり ---\n";
t('タイルが出る',       substr_count($normal, 'class="fhs-tile-input"'), 13);
t('2ステップのまま',    strpos($normal, 'data-step="2"') !== false, true);
t('ドロップダウンにしない', strpos($normal, '<select name="ptype"') !== false, false);

echo "\n--- 7. 送信の通し（子プロセス） ---\n";
$php = PHP_BINARY; $state = $GLOBALS['FAKE_STATE_FILE'];
function submit($post) {
    global $php, $state;
    $f = __DIR__ . '/designs_post.json';
    file_put_contents($f, json_encode($post, JSON_UNESCAPED_UNICODE));
    $out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/submit_case.php')
                    . ' ' . escapeshellarg($f) . ' ' . escapeshellarg($state) . ' 2>&1');
    @unlink($f);
    unset($GLOBALS['FAKE_STATE']);
    return json_decode((string) $out, true);
}
function last_row() {
    $s = fake_state();
    $r = isset($s['rows']['wp_eamber_form_leads']) ? $s['rows']['wp_eamber_form_leads'] : array();
    return $r ? $r[count($r) - 1] : array();
}

/* simple：番地なし・工事内容の質問なしでも通る */
$r = submit(array(
    'ptype' => 'other', 'simple' => '1', 'compact' => '1',
    'address' => '甲府市', 'agree' => '1',
    'customer_name' => '山田 太郎', 'customer_tel' => '090-1111-2222',
    'situation_detail' => 'ブレーカーが落ちます', 'email' => '',
));
t('★simple が受理される', is_array($r) && $r['ok'] === true, true);
$row = last_row();
t('工事内容は「その他」で残る', $row['ptype'], 'other');
t('ご相談内容が残る',           $row['detail'], 'ブレーカーが落ちます');
t('番地は空のまま',             (string) $row['address_detail'], '');
$st = fake_state();
$notify = $st['mails'][count($st['mails']) - 1]['body'];
t('通知メールに正式名が出る', strpos($notify, 'その他の問い合わせ・相談') !== false, true);

/* simple：ご相談内容が空なら弾く */
$r = submit(array(
    'ptype' => 'other', 'simple' => '1', 'compact' => '1',
    'address' => '甲府市', 'agree' => '1',
    'customer_name' => '山田 太郎', 'customer_tel' => '090-3333-4444',
    'situation_detail' => '', 'email' => '',
));
t('ご相談内容が空なら弾く',
  is_array($r) && !empty($r['errors']) && strpos(implode('', $r['errors']), 'ご相談内容') !== false, true);

/* select：追加した工事メニューでも通る */
$r = submit(array(
    'ptype' => 'solar', 'compact' => '1',
    'address' => '甲府市', 'address_detail' => '丸の内1-2-3', 'agree' => '1',
    'solar__sl_work' => '新しく設置したい',
    'customer_name' => '鈴木 花子', 'customer_tel' => '090-5555-6666', 'email' => '',
));
t('★select（太陽光）が受理される', is_array($r) && $r['ok'] === true, true);
$row = last_row();
t('工事内容が残る',   $row['ptype'], 'solar');
t('枝の答えが残る',   strpos((string) $row['details'], '新しく設置したい') !== false, true);

/* select：枝の必須が空なら弾く（分岐が効いている証拠） */
$r = submit(array(
    'ptype' => 'battery', 'compact' => '1',
    'address' => '甲府市', 'address_detail' => '丸の内1-2-3', 'agree' => '1',
    'customer_name' => '佐藤 次郎', 'customer_tel' => '090-7777-8888', 'email' => '',
));
t('枝の必須が空なら弾く',
  is_array($r) && !empty($r['errors']) && strpos(implode('', $r['errors']), 'ご希望の内容') !== false, true);

echo "\n--- 8. 設定画面に使い方が載っているか ---\n";
/* ★readme（配布zipの中）にだけ書いても、使う人の目には入らない。
     設定画面の「ショートコード」タブに出ていないものは、無いのと同じ。 */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $sp = ob_get_clean();
$GLOBALS['FAKE_IS_ADMIN'] = false;
foreach (array(
    '[eamber_form]'                  => '標準（タイル2ステップ）',
    '[eamber_form design="simple"]'  => 'シンプル',
    '[eamber_form design="select"]'  => '選択式',
    '[eamber_form design="compact"]' => 'コンパクト',
    '[eamber_form design="card"]'    => 'カード',
    '[eamber_form design="teaser"]'  => 'ティザー',
) as $code => $label) {
    t('設定画面に載っている: ' . $label,
      strpos($sp, htmlspecialchars($code, ENT_QUOTES)) !== false || strpos($sp, $code) !== false, true);
}
t('シンプルの中身を説明している', strpos($sp, '工事内容を聞かず、1画面で終わる形') !== false, true);
t('選択式の中身を説明している',   strpos($sp, '選んだ内容に応じて次の項目が変わる形') !== false, true);
/* コピーして貼れる形になっていること（このタブはコピー用の印を使う） */
t('シンプルはコピーできる',
  strpos($sp, 'class="fhs-copy-src">[eamber_form design="simple"]') !== false, true);
t('選択式はコピーできる',
  strpos($sp, 'class="fhs-copy-src">[eamber_form design="select"]') !== false, true);

echo "\n--- 9. ティザーはそのまま残る ---\n";
$teaser = eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/'));
t('ティザーは今までどおり出る', strpos($teaser, 'fhs-design-teaser') !== false, true);
t('ティザーのタイルも13枚',     substr_count($teaser, 'class="fhs-tile-input"'), 13);

echo "\n--- 10. 自己診断 ---\n";
t('自己診断: 3つの型はそれぞれ違う中身', $simple !== $select && $select !== $normal, true);
t('自己診断: 知らない型は既定に落ちる',
  strpos(eaf_shortcode(array('design' => 'あいうえお')), 'fhs-design-default') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
