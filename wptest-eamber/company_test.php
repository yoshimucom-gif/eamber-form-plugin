<?php
/**
 * 対応会社の情報（設定タブ・フォームの欄・メール末尾）。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/comp_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }

$co = array(
    'operator_name'    => '不動産工房｜グランクルー',
    'operator_address' => '東京都世田谷区船橋1丁目13-20',
    'operator_contact' => '03-5426-0567',
    'operator_email'   => 'info@example.test',
    'operator_url'     => 'https://example.test/',
);

/* --- 1. 設定画面：専用タブに分かれている --- */
$GLOBALS['FAKE_IS_ADMIN'] = true;
set_opts($co);
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('「対応会社」タブがある',      strpos($page, 'data-tab="company"') !== false, true);
t('タブのリンクがある',              strpos($page, '>対応会社</a>') !== false, true);
t('会社名の欄が移っている',          strpos($page, '<tr><th>会社名</th>') !== false, true);
t('電話番号の欄が移っている',        strpos($page, '<tr><th>電話番号</th>') !== false, true);
t('サイトURLの欄がある',             strpos($page, 'operator_url') !== false, true);
t('問い合わせメールの欄がある',      strpos($page, 'operator_email') !== false, true);
t('基本設定から会社名が消えている',  substr_count($page, 'name="eamber_form_options[operator_name]"'), 1);
t('サイト名は基本設定に残る',        strpos($page, '<tr><th>サイト名</th>') !== false, true);
t('査定ページも基本設定に残る',      strpos($page, '<tr><th>お問い合わせページ</th>') !== false, true);

/* --- 2. メール末尾：連絡先が並ぶ --- */
$mail = eaf_mail_body(array('name' => '吉村', 'customer_details' => '', 'property_details' => ''));
t('問い合わせの案内が出る', strpos($mail, '本件に関するお問い合わせは下記までお願いいたします。') !== false, true);
t('会社名',   strpos($mail, '不動産工房｜グランクルー') !== false, true);
t('所在地',   strpos($mail, '所在地 : 東京都世田谷区船橋1丁目13-20') !== false, true);
t('電話',     strpos($mail, '電話   : 03-5426-0567') !== false, true);
t('メール',   strpos($mail, 'メール : info@example.test') !== false, true);
t('サイト',   strpos($mail, 'サイト : https://example.test/') !== false, true);
t('★「宅建業者ではない」を書かない', strpos($mail, '宅地建物取引業者ではなく') !== false, false);
t('★価格の断り書きは入れない（価格を提示していないため）', strpos($mail, '鑑定評価') !== false, false);
t('署名が二重にならない',            substr_count($mail, '不動産工房｜グランクルー'), 1);

/* --- 3. 未設定の項目は行ごと出さない --- */
set_opts(array('operator_name' => 'テスト会社'));
$mail2 = eaf_mail_body(array('name' => '吉村'));
t('未設定なら所在地の行を出さない', strpos($mail2, '所在地 :') !== false, false);
t('未設定なら電話の行を出さない',   strpos($mail2, '電話   :') !== false, false);
t('会社名だけでも案内は出る',       strpos($mail2, 'テスト会社') !== false, true);

/* --- 4. 署名2行入りの本文が保存済みでも、会社名と電話が二重にならない --- */
$old_body = "ご案内です。\n\n{operator_name}\nお問い合わせ: {operator_contact}";
set_opts($co + array('mail_body' => $old_body));
$dup = eaf_mail_body(array('name' => '吉村'));
t('会社名は1回だけ',        substr_count($dup, '不動産工房｜グランクルー'), 1);
t('電話も1回だけ',          substr_count($dup, '03-5426-0567'), 1);
t('本文の中身は残る',       strpos($dup, 'ご案内です。') !== false, true);
t('「お問い合わせ:」の残骸が出ない', strpos($dup, 'お問い合わせ: 03') !== false, false);

/* --- 5. フォームの「対応会社」欄 --- */
set_opts($co);
$html = eaf_shortcode(array());
t('フォームにサイトURLが出る',     strpos($html, 'href="https://example.test/"') !== false, true);
t('表示はhttpsを省く',             strpos($html, '>example.test</a>') !== false, true);
t('電話は既定で出さない',          strpos($html, '03-5426-0567') !== false, false);
set_opts($co + array('show_contact' => '1'));
$html2 = eaf_shortcode(array('design' => 'card'));
t('表示オンなら電話が出る',        strpos($html2, '03-5426-0567') !== false, true);
t('表示オンならメールも出る',      strpos($html2, 'info@example.test') !== false, true);

/* --- 6. 自己診断 --- */
t('自己診断: 検査が空振りしていない', strpos($mail, '───') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
