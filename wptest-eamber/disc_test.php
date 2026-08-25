<?php
/**
 * 価格に関する断り書きが、どこにも紛れ込んでいないことを確かめる。
 *
 * このプラグインは価格を一切提示しない（申し込みを受け付けるだけ）。
 * 断る対象が無いところに「鑑定評価ではありません」と書いても、
 * 申し込みをためらわせるだけで誰の役にも立たない。
 * 価格をどう受け取るべきかは、担当者が査定結果を伝える場面で説明する。
 *
 * 代わりに、受付完了メールには査定担当会社の連絡先が必ず付く。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/disc_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($name, $got, $want) {
    global $ng;
    $ok = ($got === $want);
    if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $name, var_export($got, true));
}

$KEY = '鑑定評価';
$has = function ($html) use ($KEY) {
    if ($html === null) return null;   // preg失敗を「出ていない」と誤判定しない
    return strpos($html, $KEY) !== false;
};

/* ★検索キーが壊れていないかを最初に確認する。
   ソースの文字コード事故でキーが空文字になると strpos が 0 を返し、
   すべて「出ている」と誤判定してしまう（実際にこれで嵌った）。 */
t('検索キーが正しく読めている', strlen($KEY) === 12, true);
t('自己診断: 含む文字列を検出できる',     $has('<p>これは' . $KEY . 'です</p>'), true);
t('自己診断: 無関係な文字列は検出しない', $has('<p>ただのテキスト</p>'), false);

// --- 断り書きはどこにも出ない（<script>の中も含めて全文で見る） ---------
$first = eaf_shortcode(array());   // JSはページに1回だけ出るので最初に取る
t('査定ページのフォーム（JS含む全文）', $has($first), false);
t('完了画面のテンプレート',             $has($first), false);
t('compact',                            $has(eaf_shortcode(array('design' => 'compact'))), false);
t('card',                               $has(eaf_shortcode(array('design' => 'card'))), false);
t('ティザー(横長)',                     $has(eaf_shortcode(array('design' => 'teaser',   'url' => '/contact/'))), false);
t('ティザー(縦)',                       $has(eaf_shortcode(array('design' => 'teaser-v', 'url' => '/contact/'))), false);

update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => 'テスト電気工事店', 'operator_contact' => '086-000-0000',
)));
$mail = eaf_mail_body(array('name' => '山田', 'customer_details' => '', 'property_details' => ''));
t('自動返信メール',                     $has($mail), false);
t('担当者への通知メール',               $has(eaf_admin_notify_body(array('name' => '山田'))), false);

// --- 代わりに必ず付くもの -----------------------------------------------
t('メールに連絡先の案内が付く', strpos($mail, '本件に関するお問い合わせは下記まで') !== false, true);
t('会社名も入る',               strpos($mail, 'テスト電気工事店') !== false, true);

// 本文を書き換えても消えない（テンプレートの外で連結しているため）
update_option(EAF_OPT, eaf_sanitize_options(array(
    'mail_body' => 'ご案内だけの本文',
    'site_name' => 'テスト電気工事店', 'operator_contact' => '086-000-0000',
)));
$mail2 = eaf_mail_body(array('name' => '山田'));
t('本文を書き換えても連絡先は付く', strpos($mail2, '本件に関するお問い合わせは下記まで') !== false, true);
t('二重には付かない',               substr_count($mail2, '本件に関するお問い合わせは下記まで'), 1);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
