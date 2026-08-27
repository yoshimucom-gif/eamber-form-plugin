<?php
/**
 * 2ステップ構成とメール任意の検査（2026-08-25 吉村さん指示の作り直し分）。
 *
 * ・ステップは「お困りの内容（概要）→ ご連絡先（個人情報）」の2つだけ
 * ・既定で出るのは必須項目だけ（ご状況の任意項目はすべて非表示）
 * ・メールアドレスは任意。未入力でも受け付け、受付完了メールは送らない
 *   （入力があれば形式を検証し、受付完了メールを送る）
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/se_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

/* ================= 1. 2ステップ構成 ================= */
$html = eaf_shortcode(array());
t('ステップは2つ',                substr_count($html, '<div class="fhs-formstep"'), 2);
t('ステップ名は 概要→個人情報',    strpos(fake_inline_script(), '["お困りの内容","ご連絡先"]') !== false, true);
t('「ご状況」という中間ステップが無い', strpos($html, 'ご状況') !== false, false);

/* 工事内容別の必須（エアコンなら「ご希望の作業」）は1画面目=data-step="1"の中にある */
$s1 = strpos($html, 'data-step="1"');
$s2 = strpos($html, '<div class="fhs-formstep" data-step="2"');
$acw = strpos($html, 'aircon__ac_work');
t('工事内容別の必須は1画面目にある', $s1 !== false && $s2 !== false && $acw !== false && $acw > $s1 && $acw < $s2, true);
$nm = strpos($html, 'customer_name');
t('お名前は2画面目（最後）にある', $nm !== false && $nm > $s2, true);

/* ================= 2. 既定は「必須項目だけ」 =================
   ★ホワイトリスト方式。項目を足したら、意図した増加かをここで必ず突きつける。
   （ブラックリストで「これは出ない」と数えるやり方だと、
     新しく増えた任意項目が黙って通ってしまう） */
preg_match_all('/\sname="([^"]+)"/', $html, $m);
$names = array_values(array_unique($m[1]));
sort($names);
$expected = array(
    'address',            // 市町村（必須）
    'agree',              // 同意（必須）
    'aircon__ac_work',    // 工事内容別の必須 × 8種
    'breaker__br_symptom',
    'business__bz_kind',
    'customer_name',      // お名前（必須）
    'customer_tel',       // 電話番号（必須）
    'eaf_website',        // ハニーポット（人には見えない）
    'email',              // メール（任意・常に表示）
    'fan__fn_place',
    'intercom__ic_symptom',
    'light__lt_work',
    'other__ot_note',
    'outlet__ol_work',
    'ptype',              // 工事内容（必須・タイル）
    'wiring__wr_work',
);
sort($expected);
t('既定で出る入力欄は必須＋メール＋同意だけ', $names, $expected);

/* ================= 2c. 工事内容はタイルで選ばせる ================= */
t('工事内容にセレクトを使っていない', strpos($html, '<select name="ptype"') !== false, false);
t('タイルは9枚',                     substr_count($html, 'class="fhs-tile-input"'), 9);
/* CSS側にも .fhs-tile-ico が3回出るので、マークアップの class 属性だけを数える */
t('タイルにアイコンが入る',           substr_count($html, 'class="fhs-tile-ico"'), 9);
t('タイルに代表例が添えてある',       substr_count($html, 'class="fhs-tile-n"'), 9);
t('住宅配線のタイルがある',           strpos($html, 'value="wiring"') !== false, true);
/* ★工事以外の用件（提携の営業・リピートのご連絡）も拾う枠。
     「まだ決まっていない」だと工事の話に見えるので中立の表記に変えた。 */
t('「その他」のタイルがある',         strpos($html, 'その他の問い合わせ・相談') !== false, true);
t('「まだ決まっていない」は残っていない', strpos($html, 'まだ決まっていない') !== false, false);
t('問いかけが見出しとして出る',       strpos($html, 'どんなことでお困りですか？') !== false, true);
/* 自由記述の見出しは、タイルの問いかけと同じ文言にしない（同じ画面で2回同じことを言う） */
t('自由記述の見出しはお問い合わせ内容', strpos($html, '>お問い合わせ内容') !== false, true);
t('見出しがタイルの問いかけと重複しない', strpos($html, '>どんなことでお困りですか<') !== false, false);
/* ★ラジオはタイルの直前に置く。間に要素が挟まると隣接セレクタ（+）が外れ、
     選択しても色が変わらないフォームになる。 */
t('ラジオとタイルが隣接している',
  preg_match('/class="fhs-tile-input">\s*<label class="fhs-tile /', $html) === 1, true);

/* その他だけは自由記述が必須（ここを非表示にすると何も伝えられなくなる） */
$ot_def = '';
foreach (eaf_property_fields()['other'] as $fd) if ($fd['key'] === 'ot_note') $ot_def = $fd['def'];
t('「その他」の自由記述は必須', $ot_def, 'req');

/* ================= 2b. 保存済みモードの一度きりのリセット =================
   ★これが無いと「既定を変えたのに、一度でも設定を保存した環境では変わらない」。 */
$o = get_option(EAF_OPT, array());
$o['mode_situation_building'] = 'req';        // 旧版で保存された状態を再現
$o['mode_customer_contact_time'] = 'opt';
$o['show_note'] = '1';                        // 廃止済みの設定（掃除の対象）
$o['show_marketing'] = '1';                   // 営業案内チェックは別フラグで出る
$o['spam_block_link'] = '1';                  // URLブロックも別フラグ
$o['site_name'] = '不動産査定';                // フォーク元の既定が保存されたまま
update_option(EAF_OPT, $o);
delete_option('eaf_field_defaults_ver');       // 未移行の環境を再現
t('移行前は保存値が効いている', eaf_mode('situation', 'building', 'off'), 'req');
t('移行前は営業案内チェックが出てしまう', eaf_flag('show_marketing', false), true);
t('移行前はURLブロックが効いてしまう',   eaf_flag('spam_block_link', false), true);
eaf_maybe_reset_field_modes();
t('移行で保存値が捨てられ既定に戻る', eaf_mode('situation', 'building', 'off'), 'off');
t('営業案内チェックも既定（非表示）に戻る', eaf_flag('show_marketing', false), false);
t('URLブロックも既定（オフ）に戻る',       eaf_flag('spam_block_link', false), false);
t('フォーク元の社名も捨てる',         eaf_opt('site_name', '株式会社e.Amber'), '株式会社e.Amber');
t('移行は版を記録して二度実行しない', get_option('eaf_field_defaults_ver'), EAF_FIELD_DEFAULTS_VER);
/* ★捨てるのは「フォーク元の既定そのもの」だけ。手で入れた社名まで消してはいけない */
$o = get_option(EAF_OPT, array());
$o['site_name'] = '株式会社e.Amber 山梨営業所';
update_option(EAF_OPT, $o);
delete_option('eaf_field_defaults_ver');
eaf_maybe_reset_field_modes();
t('自分で入れた社名は捨てない', eaf_opt('site_name', ''), '株式会社e.Amber 山梨営業所');
$o = get_option(EAF_OPT, array());
$o['mode_situation_building'] = 'req';         // 移行後に吉村さんが自分で戻した状態
update_option(EAF_OPT, $o);
eaf_maybe_reset_field_modes();
t('移行後の設定は二度と壊さない', eaf_mode('situation', 'building', 'off'), 'req');
update_option(EAF_OPT, eaf_sanitize_options(array()));   // 後続の検査のため既定へ戻す

/* ================= 3. メール欄は任意 ================= */
t('メール欄に required が無い', preg_match('/name="email"[^>]*\srequired/', $html) === 1, false);
t('メールのラベルが「任意」',   preg_match('/メールアドレス<span class="fhs-opt">任意<\/span>/', $html) === 1, true);

/* ================= 4. 送信の通し（子プロセス） ================= */
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1',
)));
eaf_activate();

$php  = PHP_BINARY;
$state = $GLOBALS['FAKE_STATE_FILE'];
function submit($post) {
    global $php, $state;
    $f = __DIR__ . '/se_post.json';
    file_put_contents($f, json_encode($post, JSON_UNESCAPED_UNICODE));
    $out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/submit_case.php') . ' '
                    . escapeshellarg($f) . ' ' . escapeshellarg($state) . ' 2>&1');
    @unlink($f);
    // 状態ファイルは子プロセスが更新しているので読み直す
    unset($GLOBALS['FAKE_STATE']);
    return json_decode((string)$out, true);
}

$base = array(
    'ptype' => 'aircon', 'address' => '甲府市', 'agree' => '1',
    'aircon__ac_work' => '入れ替えたい',
    'customer_name' => '山田 太郎', 'customer_tel' => '090-1234-5678',
);

/* メール無しでも受け付ける */
$r1 = submit($base + array('email' => ''));
t('メール無しで受理される', is_array($r1) && $r1['ok'] === true, true);
$s = fake_state();
$mails1 = isset($s['mails']) ? count($s['mails']) : 0;
$rows1  = isset($s['rows']['wp_eamber_form_leads']) ? count($s['rows']['wp_eamber_form_leads']) : 0;
t('リードは保存される', $rows1, 1);
t('お客様宛メールは送らない（通知のみ）', $mails1, 1);   // 担当者通知の1通だけ

/* メールがあれば受付完了メール＋通知の2通 */
$r2 = submit($base + array('email' => 'taro@example.test', 'customer_tel' => '090-2222-3333'));
t('メール有りでも受理される', is_array($r2) && $r2['ok'] === true, true);
$s = fake_state();
t('受付完了＋通知の2通になる', count($s['mails']), 3);
t('受付完了メールの宛先', $s['mails'][1]['to'], 'taro@example.test');

/* 形式の悪いメールは弾く（入力がある場合だけ検証） */
$r3 = submit($base + array('email' => 'kowareta@', 'customer_tel' => '090-4444-5555'));
t('形式不正は弾く', is_array($r3) && !empty($r3['errors']), true);

/* 市町村の選択肢外はフォーム改ざんとして弾く */
$r4 = submit(array_merge($base, array('address' => '東京都渋谷区1-2-3', 'email' => '')));
t('選択肢外の市町村は弾く', is_array($r4) && !empty($r4['errors']), true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
