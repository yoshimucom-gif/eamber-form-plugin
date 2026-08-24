<?php
/**
 * フォーム上の問い合わせ先（電話番号）の表示制御。
 * 既定は非表示。フォームに電話番号があると、送信せず電話で済ませる人が出て
 * 申し込み数が落ちるため。メールには必ず載せる。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/contact_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }

$base = array(
    'operator_name'    => '株式会社e.Amber',
    'operator_address' => '山梨県甲府市○○1-2-3',
    'operator_contact' => '055-000-0000',
    'privacy_url'      => 'https://example.test/privacy',
);

/* --- 既定（未設定）ではフォームに出ない --- */
set_opts($base);
$html = eaf_shortcode(array());
t('既定ではフォームに電話番号を出さない', strpos($html, '055-000-0000') !== false, false);
t('会社名は出る',   strpos($html, '株式会社e.Amber') !== false, true);
t('所在地も出る',   strpos($html, '山梨県甲府市○○1-2-3') !== false, true);
t('削除依頼はポリシーへ案内', strpos($html, 'に記載の窓口までお申し付けください') !== false, true);

/* --- 受付完了メールには必ず載る --- */
$mail = eaf_mail_body(array('name' => '山田', 'customer_details' => '', 'property_details' => ''));
t('メールには電話番号が載る', strpos($mail, '055-000-0000') !== false, true);

/* --- ONにすればフォームにも出る --- */
set_opts($base + array('show_contact' => '1'));
$html2 = eaf_shortcode(array('design' => 'card'));
t('ONならフォームに出る',       strpos($html2, '055-000-0000') !== false, true);
t('削除依頼は「下記の連絡先」', strpos($html2, '下記の連絡先までお申し付けください') !== false, true);

/* --- 会社情報が名前だけでもブロックは出る／全部無ければ出ない --- */
set_opts(array('operator_name' => '株式会社e.Amber'));
t('名前だけでも会社欄は出る', strpos(eaf_shortcode(array('design' => 'compact')), '対応会社') !== false, true);
set_opts(array('operator_contact' => '055-000-0000'));   // 連絡先だけ・非表示設定
t('連絡先だけ かつ 非表示なら会社欄を出さない', strpos(eaf_shortcode(array('design' => 'teaser-v', 'url' => '/satei/')), '対応会社') !== false, false);

/* --- 自己診断 --- */
set_opts($base + array('show_contact' => '1'));
t('自己診断: 検査が電話番号を見つけられる', strpos(eaf_shortcode(array('design' => 'card')), '055-000-0000') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
