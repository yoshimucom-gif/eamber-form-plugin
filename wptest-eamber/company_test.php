<?php
/**
 * 会社情報（基本設定タブ・メール末尾の署名）。
 *
 * 運営＝施工＝同じ会社なので、会社名はサイト名（site_name）に一本化。
 * 連絡先（電話・問い合わせメール・所在地）は基本設定タブにあり、
 * 受付完了メールの末尾に署名として自動で入る。
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
    'site_name'        => '株式会社e.Amber',
    'operator_address' => '山梨県甲府市○○1-2-3',
    'operator_contact' => '055-000-0000',
    'operator_email'   => 'info@example.test',
);

/* --- 1. 設定画面：会社情報は基本設定タブにまとまっている --- */
$GLOBALS['FAKE_IS_ADMIN'] = true;
set_opts($co);
ob_start(); eaf_settings_page(); $page = ob_get_clean();
t('会社名（サイト名）の欄がある',    strpos($page, '<tr><th>会社名（サイト名）</th>') !== false, true);
t('電話番号の欄がある',              strpos($page, '<tr><th>電話番号（お客様向け）</th>') !== false, true);
t('問い合わせメールの欄がある',      strpos($page, 'operator_email') !== false, true);
t('所在地の欄がある',                strpos($page, 'operator_address') !== false, true);
t('会社名の入力欄は1つだけ（二重管理しない）', substr_count($page, 'name="eamber_form_options[site_name]"'), 1);
t('対応会社タブは無い',              strpos($page, '>対応会社</a>') !== false, false);
t('お問い合わせページも基本設定に残る', strpos($page, '<tr><th>お問い合わせページ</th>') !== false, true);

/* --- 2. メール末尾：署名が並ぶ --- */
$mail = eaf_mail_body(array('name' => '吉村', 'customer_details' => '', 'property_details' => ''));
t('問い合わせの案内が出る', strpos($mail, '本件に関するお問い合わせは下記までお願いいたします。') !== false, true);
t('会社名（=サイト名）',  strpos($mail, '株式会社e.Amber') !== false, true);
t('所在地',   strpos($mail, '所在地 : 山梨県甲府市○○1-2-3') !== false, true);
t('電話',     strpos($mail, '電話   : 055-000-0000') !== false, true);
t('メール',   strpos($mail, 'メール : info@example.test') !== false, true);

/* --- 3. 未設定の項目は行ごと出さない --- */
set_opts(array('site_name' => 'テスト会社'));
$mail2 = eaf_mail_body(array('name' => '吉村'));
t('未設定なら所在地の行を出さない', strpos($mail2, '所在地 :') !== false, false);
t('未設定なら電話の行を出さない',   strpos($mail2, '電話   :') !== false, false);
t('会社名だけでも案内は出る',       strpos($mail2, 'テスト会社') !== false, true);

/* --- 4. 旧テンプレの署名タグ（{operator_name}等）が保存済みでも二重にならない --- */
$old_body = "ご案内です。\n\n{operator_name}\nお問い合わせ: {operator_contact}";
set_opts($co + array('mail_body' => $old_body));
$dup = eaf_mail_body(array('name' => '吉村'));
t('会社名は1回だけ',        substr_count($dup, '株式会社e.Amber'), 1);
t('電話も1回だけ',          substr_count($dup, '055-000-0000'), 1);
t('本文の中身は残る',       strpos($dup, 'ご案内です。') !== false, true);
t('「お問い合わせ:」の残骸が出ない', strpos($dup, 'お問い合わせ: 055') !== false, false);

/* --- 5. 会社名はメール署名だけで使う（フォーム本体には出さない） --- */
set_opts($co);
$html = eaf_shortcode(array());
t('フォームに会社紹介を出さない', strpos($html, 'fhs-operator') !== false, false);
t('個人情報の説明文も出さない',   strpos($html, 'fhs-privacy-note') !== false, false);

/* --- 6. 自己診断 --- */
t('自己診断: 検査が空振りしていない', strpos($mail, '───') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
