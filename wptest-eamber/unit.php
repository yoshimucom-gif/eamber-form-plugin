<?php
/** 設定の保存まわりの単体テスト（既存プラグインで踏んだ罠の再発防止） */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/unit_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
$GLOBALS['FAKE_IS_ADMIN'] = false;
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($name, $got, $want) {
    global $ng;
    $ok = ($got === $want);
    if (!$ok) $ng++;
    printf("%s %s\n    got : %s\n    want: %s\n", $ok ? 'OK  ' : 'NG  ', $name,
        var_export($got, true), var_export($want, true));
}

/* --- 1. 通知ONのチェックを外して保存 → OFFのままか（既存で踏んだ「巻き戻り」の罠） --- */
update_option(EAF_OPT, eaf_sanitize_options(array('notify_on' => '1')));
t('通知ON→保存後もON', eaf_flag('notify_on', true), true);
update_option(EAF_OPT, eaf_sanitize_options(array()));   // チェックを外して保存（未送信）
t('通知OFF→保存後もOFF（巻き戻らない）', eaf_flag('notify_on', true), false);
update_option(EAF_OPT, eaf_sanitize_options(array()));   // もう一度保存しても戻らない
t('もう一度保存してもOFFのまま', eaf_flag('notify_on', true), false);

/* --- 2. 送信元メールを空にできるか（既存で踏んだ「空にできずSPF不整合」の罠） --- */
update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => 'a@example.test')));
t('送信元メールを設定', eaf_opt('from_email'), 'a@example.test');
update_option(EAF_OPT, eaf_sanitize_options(array('from_email' => '')));
t('送信元メールを空にできる', eaf_opt('from_email', 'DEFAULT'), 'DEFAULT');

/* --- 3. 項目モードの保存と、不正値の拒否 --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'mode_customer_name' => 'off',
    'mode_customer_tel'  => 'opt',
    'mode_situation_ownership' => 'req',
    'mode_prop_aircon_ac_work' => '<script>',      // 不正値（ac_workの既定はreq）
)));
t('お名前=非表示', eaf_mode('customer', 'name', 'req'), 'off');
t('電話=任意',     eaf_mode('customer', 'tel', 'req'), 'opt');
t('持ち家か賃貸か=必須', eaf_mode('situation', 'ownership', 'off'), 'req');
t('不正値は既定に戻る', eaf_mode('prop_aircon', 'ac_work', 'req'), 'req');
t('未送信の項目は既定', eaf_mode('prop_intercom', 'ic_symptom', 'req'), 'req');

/* --- 4. 表示対象の抽出 --- */
$vis = array_column(eaf_visible_fields('customer', eaf_customer_fields()), 'key');
t('非表示にした項目は出ない', in_array('name', $vis, true), false);
$req = array_column(eaf_visible_fields('customer', eaf_customer_fields(), true), 'key');
t('compactは必須のみ（電話は任意なので出ない）', in_array('tel', $req, true), false);

/* --- 5. 入力の正規化 --- */
t('全角数字→半角', eaf_to_hankaku('２０１５'), '2015');
t('全角ハイフン→半角', eaf_to_hankaku('０９０－１２３４'), '090-1234');
t('電話9桁OK',  eaf_tel_valid('06-123-4567'), true);
t('電話8桁NG',  eaf_tel_valid('06-123-456'), false);
t('電話12桁NG', eaf_tel_valid('090-1234-56789'), false);
t('文字数で丸める', eaf_trim_len('あいうえお', 3), 'あいう');
t('短い文字列はそのまま', eaf_trim_len('あい', 10), 'あい');

/* --- 6. 連絡先はテンプレートを書き換えても必ず付く --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'mail_body' => 'ご案内だけ書いた本文', 'operator_name' => 'テスト電気工事店',
)));
$body = eaf_mail_body(array('name' => '山田', 'customer_details' => '', 'property_details' => ''));
t('連絡先が自動で付く', strpos($body, '本件に関するお問い合わせは下記まで') !== false, true);
t('★旧業種（査定）の文言が出ない', strpos($body, '査定') !== false, false);
$body2 = eaf_mail_body(array('name' => '山田'));
t('二重には付かない', substr_count($body2, '本件に関するお問い合わせは下記まで'), 1);

/* --- 7. 差し込みタグと空欄の行削除 --- */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'mail_body' => "{customer_name}様\n\n{customer_details}\n\n{operator_name}\nお問い合わせ: {operator_contact}",
    'operator_name' => '', 'operator_contact' => '',
)));
$b3 = eaf_mail_body(array('name' => '', 'customer_details' => '■ 電話 : 090'));
t('お名前が空なら「様」の行ごと消える', strpos($b3, '様') === false, true);
t('問い合わせ先が空ならラベルごと消える', strpos($b3, 'お問い合わせ:') === false, true);
t('空行が3連続以上残らない', preg_match('/\n{3,}/', $b3) === 0, true);

/* --- 7.5 ティザーの見出しまわり（バッジ・メリットのタグ） --- */
t('タグ: カンマ区切り',   eaf_split_tags('無料, 地場優良企業対応, 1社査定'), array('無料','地場優良企業対応','1社査定'));
t('タグ: 読点でも切れる', eaf_split_tags('無料、秘密厳守'), array('無料','秘密厳守'));
t('タグ: 全角カンマ',     eaf_split_tags('Ａ，Ｂ'), array('Ａ','Ｂ'));
t('タグ: 縦棒',           eaf_split_tags('A|B'), array('A','B'));
t('タグ: 余分な空白と空要素を落とす', eaf_split_tags(' A , , B  '), array('A','B'));
t('タグ: 空欄なら空配列',  eaf_split_tags(''), array());
// 設定していなければバッジもタグも出さない（既定の文言は持たない）
update_option(EAF_OPT, eaf_sanitize_options(array('teaser_badge' => '', 'teaser_tags' => '')));
$tz = eaf_shortcode(array('design' => 'teaser', 'url' => '/satei/'));
t('未設定ならバッジは出ない', strpos($tz, 'fhs-tbadge">') !== false, false);
t('未設定ならタグは出ない',   strpos($tz, 'fhs-ttag">') !== false, false);
update_option(EAF_OPT, eaf_sanitize_options(array('teaser_badge' => '無料・秘密厳守', 'teaser_tags' => '無料,1社査定')));
$tz2 = eaf_shortcode(array('design' => 'teaser', 'url' => '/satei/'));
t('設定すればバッジが出る', strpos($tz2, '>無料・秘密厳守</span>') !== false, true);
t('設定すればタグが出る',   substr_count($tz2, 'class="fhs-ttag"'), 2);
t('縦ティザーにも出る',     substr_count(eaf_shortcode(array('design' => 'teaser-v', 'url' => '/satei/')), 'class="fhs-ttag"'), 2);
t('本フォームには出ない',   strpos(eaf_shortcode(array()), 'class="fhs-ttag"') !== false, false);

/* --- 8. CSVインジェクション --- */
t('=で始まる値をエスケープ', eaf_csv_safe('=cmd|calc'), "'=cmd|calc");
t('数値はそのまま', eaf_csv_safe('-5'), '-5');
t('通常の文字列はそのまま', eaf_csv_safe('山田太郎'), '山田太郎');

/* --- 9. 保存カラムの定義がスキーマと一致しているか --- */
$cols = eaf_lead_columns();
/* ★会社名は「工事内容ごとの項目」側にあるカラム。ここに出てこないと
     ALTER が走らず、法人の反響が insert ごと失敗して1件も残らない。 */
t('保存カラム一覧', array_keys($cols), array('name','kana','tel','contact_time','address_detail','building','ownership','timing','detail','company'));

/* --- 10. 見出しの一致（フォーム・CSV・スプレッドシート） ---
   ★同じ項目の見出しが3か所に散っている。1か所だけ直して他が古いまま残ると、
     担当者は「フォームの◯◯」と「CSVの△△」が同じものだと気づけない。 */
$label_of = function ($key) {
    foreach (eaf_situation_fields() as $fd) if ($fd['key'] === $key) return $fd['label'];
    return '';
};
t('ご相談内容の見出し', $label_of('detail'), 'ご相談内容');
/* ★「症状」はブレーカー等の別項目でも使う語なので、この欄の例文だけを見る */
$ph_of = function ($key) {
    foreach (eaf_situation_fields() as $fd) if ($fd['key'] === $key) return isset($fd['ph']) ? $fd['ph'] : '';
    return '';
};
t('書き方の例も入れ替わっている', $ph_of('detail'), '例：ご相談内容を簡単に記入してください。');

$gas = eaf_sheet_gas_code();
t('スプレッドシートの見出しも同じ', strpos($gas, "'" . $label_of('detail') . "'") !== false, true);
t('古い見出しは残っていない',       strpos($gas, '症状・ご希望') !== false, false);

/* CSVは出力後に exit するので、子プロセスで受け取る */
$csv = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/sec_case.php')
     . ' ' . escapeshellarg('eaf_export_leads') . ' ' . escapeshellarg('manage_options')
     . ' 0 ' . escapeshellarg('eaf_export_leads') . ' 2>&1');
if (function_exists('mb_convert_encoding')) $csv = mb_convert_encoding($csv, 'UTF-8', 'SJIS-win');
t('自己診断: CSVを受け取れている', strpos($csv, '受付日時') !== false, true);
t('CSVの見出しも同じ',             strpos($csv, $label_of('detail')) !== false, true);
t('CSVに古い見出しが残っていない', strpos($csv, '症状・ご希望') !== false, false);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
exit($ng ? 1 : 0);
