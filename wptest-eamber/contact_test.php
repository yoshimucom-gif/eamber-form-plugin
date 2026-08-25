<?php
/**
 * 電話番号の表示制御。
 *
 * 本気査定は「電話で済まされると申込が減る」ため電話をフォームで伏せていたが、
 * eamber-form は自社サイトの工事問い合わせ＝電話も同じ受注なので方針が逆:
 * 電話番号（operator_contact）が設定されていれば、フォームの一番上に
 * 「お急ぎの方はお電話が早いです」を必ず出す（設定フラグは無い）。
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

/* --- 1. 電話未設定なら電話バーは出ない --- */
set_opts(array());
$h0 = eaf_shortcode(array());
t('未設定なら電話バーは出ない', strpos($h0, 'お急ぎの方はお電話が早いです') !== false, false);

/* --- 2. 電話を設定するとフォーム冒頭に出る --- */
set_opts(array('operator_contact' => '055-000-0000'));
$h1 = eaf_shortcode(array());
t('電話バーが出る',            strpos($h1, 'お急ぎの方はお電話が早いです') !== false, true);
t('番号が表示される',          strpos($h1, '055-000-0000') !== false, true);
t('tel:リンクになる',          strpos($h1, 'href="tel:0550000000"') !== false, true);
t('冒頭（フォームより前）に出る', strpos($h1, 'お急ぎの方はお電話が早いです') < strpos($h1, '<form class="fhs-form">'), true);

/* --- 3. card / compact でも出る。ティザーには出ない（入口は軽く保つ） --- */
t('cardでも出る',       strpos(eaf_shortcode(array('design' => 'card')), 'お急ぎの方はお電話が早いです') !== false, true);
t('compactでも出る',    strpos(eaf_shortcode(array('design' => 'compact')), 'お急ぎの方はお電話が早いです') !== false, true);
t('ティザーには出ない', strpos(eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/')), 'お急ぎの方はお電話が早いです') !== false, false);

/* --- 4. 旧 show_contact フラグは保存されない --- */
set_opts(array('operator_contact' => '055-000-0000', 'show_contact' => '1'));
$o = get_option(EAF_OPT, array());
t('show_contact は保存されない', array_key_exists('show_contact', $o), false);

/* --- 自己診断 --- */
t('自己診断: 検査が電話番号を見つけられる', strpos($h1, '055-000-0000') !== false, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
