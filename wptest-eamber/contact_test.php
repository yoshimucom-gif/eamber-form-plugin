<?php
/**
 * フォーム冒頭の電話案内。
 *
 * 本気査定は「電話で済まされると申込が減る」ため電話を伏せていたが、
 * eamber-form は自社サイトの工事問い合わせ＝電話も同じ受注なので方針が逆。
 * 電話番号が入っていれば既定で一番上に出す。
 * 文言は「メッセージ」「サブメッセージ」で差し替えられ、
 * ★出す・出さないは3段それぞれチェックで切り替えられる
 *   （文言を消さずに止められること。とくにメッセージは既定文があるため、
 *     空欄にしても消えない＝チェックが無いと止める手段が無かった）。
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
/* ★設定画面のチェックボックスは、チェック済みなら値が送られてくる。
     ここで補わないと「全部オフで保存した状態」を検査することになる。 */
function set_opts($a) {
    $a += array('show_telbar' => '1', 'show_tel_message' => '1', 'show_tel_sub' => '1');
    update_option(EAF_OPT, eaf_sanitize_options($a));
}
function has($h, $s) { return strpos($h, $s) !== false; }

$DEFAULT_MSG = 'お急ぎの方はお電話ください。';
/* ★CSSにも .fhs-telbar が入る。クラス名だけで探すと、電話未設定でも
     「出ている」と誤判定する（最初の1回はCSSが一緒に出力されるため）。 */
define('TELBAR', '<div class="fhs-telbar">');

/* --- 1. 電話未設定なら出さない --- */
set_opts(array());
$h0 = eaf_shortcode(array());
t('未設定なら電話案内を出さない', has($h0, TELBAR), false);

/* --- 2. 電話を入れるとフォーム冒頭に出る --- */
set_opts(array('operator_contact' => '090-3451-6042'));
$h1 = eaf_shortcode(array());
t('電話案内が出る',        has($h1, TELBAR), true);
t('既定のメッセージ',      has($h1, $DEFAULT_MSG), true);
t('番号が表示される',      has($h1, '090-3451-6042'), true);
t('tel:リンクはハイフンを外す', has($h1, 'href="tel:09034516042"'), true);
t('冒頭（フォームより前）に出る', strpos($h1, TELBAR) < strpos($h1, '<form class="fhs-form">'), true);
/* ★以前は「お電話が早いです：番号」と1行に押し込んでいて日本語として不自然だった。
     メッセージ／番号／サブの3段に分ける。 */
t('番号を文中に押し込まない', has($h1, 'が早いです'), false);
t('サブは未設定なら出さない', has($h1, 'class="fhs-telbar-sub"'), false);

/* --- 3. 文言を差し替えられる --- */
set_opts(array('operator_contact' => '090-3451-6042',
               'tel_message'    => 'まずはお気軽にお電話ください',
               'tel_submessage' => '受付 9:00〜18:00（日曜・祝日を除く）'));
$h2 = eaf_shortcode(array());
t('メッセージを差し替えられる',   has($h2, 'まずはお気軽にお電話ください'), true);
t('差し替えると既定は出ない',     has($h2, $DEFAULT_MSG), false);
t('サブメッセージが出る',         has($h2, '受付 9:00〜18:00（日曜・祝日を除く）'), true);
t('サブは番号の後ろに出る',       strpos($h2, 'class="fhs-telbar-num"') < strpos($h2, 'class="fhs-telbar-sub"'), true);
/* 入力はそのまま出さずエスケープする（設定画面から入る値なので念のため） */
set_opts(array('operator_contact' => '090-3451-6042', 'tel_message' => '<script>alert(1)</script>'));
$h3 = eaf_shortcode(array());
t('メッセージをエスケープする', has($h3, '<script>alert(1)</script>'), false);

/* --- 3b. 出す・出さないを3段それぞれ切り替えられる --- */
$copy = array('operator_contact' => '090-3451-6042',
              'tel_message'    => 'まずはお気軽にお電話ください',
              'tel_submessage' => '受付 9:00〜18:00（日曜・祝日を除く）');

/* 一度も保存していない状態＝既定は「出す」 */
delete_option(EAF_OPT);
update_option(EAF_OPT, array('operator_contact' => '090-3451-6042'));
t('既定（未保存）では電話案内が出る', has(eaf_shortcode(array()), TELBAR), true);

/* 枠ごと止める。文言は消さない */
set_opts(array_merge($copy, array('show_telbar' => '')));
$hx = eaf_shortcode(array());
t('チェックを外すと電話案内が消える', has($hx, TELBAR), false);
t('番号もフォームには出ない',         has($hx, '090-3451-6042'), false);
t('文言は設定に残っている',           eaf_opt('tel_message'), 'まずはお気軽にお電話ください');

/* メッセージだけ止める＝番号とサブは残る */
set_opts(array_merge($copy, array('show_tel_message' => '')));
$hy = eaf_shortcode(array());
t('枠は出たまま',                   has($hy, TELBAR), true);
t('メッセージだけ消える',           has($hy, 'まずはお気軽にお電話ください'), false);
t('既定文に戻ったりしない',         has($hy, $DEFAULT_MSG), false);
t('番号は残る',                     has($hy, '090-3451-6042'), true);
t('サブも残る',                     has($hy, '受付 9:00〜18:00（日曜・祝日を除く）'), true);

/* サブだけ止める＝メッセージと番号は残る */
set_opts(array_merge($copy, array('show_tel_sub' => '')));
$hz = eaf_shortcode(array());
t('サブだけ消える',       has($hz, 'class="fhs-telbar-sub"'), false);
t('メッセージは残る',     has($hz, 'まずはお気軽にお電話ください'), true);
t('番号は残る',           has($hz, '090-3451-6042'), true);

/* 自己診断：全部オンなら3段そろって出る */
set_opts($copy);
$ha = eaf_shortcode(array());
t('自己診断: 全部オンなら3段そろう',
  has($ha, 'class="fhs-telbar-msg"') && has($ha, 'class="fhs-telbar-num"')
  && has($ha, 'class="fhs-telbar-sub"'), true);

/* --- 3c. 設定画面に、切り替えのチェックが実際に並んでいるか --------------
     ★描画側だけ直してチェック欄を出し忘れると、一度オフにした人が
       二度と戻せなくなる。画面に出ていることまで見る。 */
$GLOBALS['FAKE_IS_ADMIN'] = true;
ob_start(); eaf_settings_page(); $page = ob_get_clean();
foreach (array('show_telbar'      => '電話案内そのもの',
               'show_tel_message' => 'メッセージ',
               'show_tel_sub'     => 'サブメッセージ',
               'show_lead'        => 'フォーム冒頭の案内文',
               'show_city_hint'   => '市町村欄の補足文') as $k => $label) {
    t('設定画面に「' . $label . '」の切り替えがある',
      preg_match('/type="checkbox" name="[^"]*\[' . $k . '\]"/', $page) === 1, true);
}
$GLOBALS['FAKE_IS_ADMIN'] = false;

/* --- 4. どのデザインでも出す。ティザーには出さない（入口は軽く保つ） --- */
set_opts(array('operator_contact' => '090-3451-6042'));
t('cardでも出る',       has(eaf_shortcode(array('design' => 'card')), TELBAR), true);
t('compactでも出る',    has(eaf_shortcode(array('design' => 'compact')), TELBAR), true);
t('ティザーには出ない', has(eaf_shortcode(array('design' => 'teaser', 'url' => '/contact/')), TELBAR), false);

/* --- 4b. 帯の色を選べる --- */
set_opts(array('operator_contact' => '090-3451-6042',
               'color_tel_bg' => '#fff8e6', 'color_tel_bd' => '#e8c98a', 'color_tel_fg' => '#7a5310'));
/* ★CSSは1リクエストに1回しか積まれない（ハンドルで重複を防ぐ本物と同じ挙動）。
     設定を変えた効果を見るには、積み直しの状態に戻してから描画する。 */
fake_assets_reset();
eaf_shortcode(array());
$css = fake_inline_style();
t('背景色が入る', strpos($css, '--fhs-tel-bg:#fff8e6') !== false, true);
t('線の色が入る', strpos($css, '--fhs-tel-bd:#e8c98a') !== false, true);
t('文字色が入る', strpos($css, '--fhs-tel-fg:#7a5310') !== false, true);
t('番号も文字色に従う', strpos($css, '.fhs-wrap .fhs-telbar-num{display:inline-flex;align-items:center;gap:7px;color:var(--fhs-tel-fg)') !== false, true);

/* --- 5. 廃止した設定は保存しない --- */
set_opts(array('operator_contact' => '090-3451-6042', 'show_contact' => '1'));
$o = get_option(EAF_OPT, array());
t('show_contact は保存されない', array_key_exists('show_contact', $o), false);

/* --- 自己診断 --- */
t('自己診断: 検査が番号を見つけられている', has($h1, '090-3451-6042'), true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
