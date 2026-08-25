<?php
/**
 * 2ステップ構成とメール任意の検査（2026-08-25 吉村さん指示の作り直し分）。
 *
 * ・ステップは「お困りの内容（概要）→ ご連絡先（個人情報）」の2つだけ
 * ・ご状況の項目は1画面目に同居し、既定で出すのは建物・時期・自由記述だけ
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
t('ステップ名は 概要→個人情報',    strpos($html, '["お困りの内容","ご連絡先"]') !== false, true);
t('「ご状況」という中間ステップが無い', strpos($html, 'ご状況') !== false, false);

/* ご状況の項目（建物の種類）は1画面目=data-step="1"の中にある */
$s1 = strpos($html, 'data-step="1"');
$s2 = strpos($html, '<div class="fhs-formstep" data-step="2"');
$bld = strpos($html, 'situation_building');
t('建物の種類は1画面目にある', $s1 !== false && $s2 !== false && $bld !== false && $bld > $s1 && $bld < $s2, true);
$nm = strpos($html, 'customer_name');
t('お名前は2画面目（最後）にある', $nm !== false && $nm > $s2, true);

/* ================= 2. 既定の項目の絞り込み ================= */
foreach (array('situation_ownership' => '持ち家か賃貸か', 'situation_since' => 'いつから',
               'customer_contact_way' => '連絡方法', 'aircon__ac_model' => '型番',
               'breaker__br_age' => '分電盤の設置年', 'intercom__ic_year' => '築年',
               'note_text' => '備考・ご要望') as $key => $label) {
    t('既定で出ない: ' . $label, strpos($html, 'name="' . $key . '"') !== false, false);
}
$bld_def = '';
foreach (eaf_situation_fields() as $fd) if ($fd['key'] === 'building') $bld_def = $fd['def'];
t('建物の種類は任意になっている', $bld_def, 'opt');

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
