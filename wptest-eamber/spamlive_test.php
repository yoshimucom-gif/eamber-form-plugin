<?php
/**
 * 英語スパムが実際に通ってしまうかを、送信の通しで確かめる。
 *
 * spam_test.php は判定の関数だけを見ている。こちらは
 * 「既定の設定のまま、フォームに投げたら受理されるのか」を見る。
 * ★既定でどこまで止まるのかを、思い込みでなく記録に残すのが目的。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/spamlive_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}

update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1',
)));
eaf_activate();

$php = PHP_BINARY; $state = $GLOBALS['FAKE_STATE_FILE'];
/** 1件送る。$extra で経過時間やハニーポットも差し替えられる */
function send($over = array(), $tel = null) {
    global $php, $state;
    static $n = 0; $n++;
    $post = array_merge(array(
        'ptype' => 'aircon', 'address' => '甲府市', 'address_detail' => '丸の内1-2-3',
        'agree' => '1', 'aircon__ac_work' => '入れ替えたい',
        'customer_name' => '山田 太郎',
        'customer_tel' => $tel !== null ? $tel : sprintf('090-1234-%04d', 1000 + $n),
        'email' => '',
    ), $over);
    $f = __DIR__ . '/spamlive_post.json';
    file_put_contents($f, json_encode($post, JSON_UNESCAPED_UNICODE));
    $out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/submit_case.php')
                    . ' ' . escapeshellarg($f) . ' ' . escapeshellarg($state) . ' 2>&1');
    @unlink($f);
    unset($GLOBALS['FAKE_STATE']);
    $r = json_decode((string) $out, true);
    return is_array($r) && !empty($r['ok']);   // true = 受理された
}

echo "--- 1. ボットのふるまいは既定で止まる ---\n";
t('ハニーポットに書き込むと止まる', send(array('eaf_website' => 'http://spam.test')), false);
/* 経過時間は子プロセス側で固定されるので、判定そのものを直接見る */
$_POST = array('eaf_elapsed' => '900');
t('速すぎる送信は止まる',           eaf_bot_errors() !== array(), true);
$_POST = array('eaf_elapsed' => '9999');
t('ふつうの速さなら止めない',       eaf_bot_errors(), array());
$_POST = array();

echo "\n--- 2. 文字種で止まるもの（設定不要・常時） ---\n";
t('キリル文字は止まる', send(array('customer_name' => 'Привет Мир')), false);
t('アラビア文字は止まる', send(array('address_detail' => 'مرحبا بالعالم')), false);
t('タイ文字は止まる',   send(array('address_detail' => 'สวัสดี')), false);

echo "\n--- 3. ★英語のスパムは既定で通ってしまうか ---\n";
/* 実際に日本のフォームへ届く典型。リンクを含まず、名前もラテン文字だけ。 */
$pitch = 'Hello, I noticed your website and I can improve your Google ranking. '
       . 'We offer affordable SEO packages. Please reply for details.';
t('★英語の営業文（リンク無し）は通る', send(array(
    'customer_name' => 'John Smith', 'address_detail' => $pitch)), true);
t('★リンク付きも既定では通る', send(array(
    'customer_name' => 'Mike Brown',
    'address_detail' => 'Visit https://cheap-seo.example for details')), true);

echo "\n--- 4. 設定を入れれば止まる ---\n";
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1',
    'spam_block_link' => '1')));
t('リンク遮断をオンにすると止まる', send(array(
    'customer_name' => 'Mike Brown',
    'address_detail' => 'Visit https://cheap-seo.example for details')), false);
t('リンクの無い英語文はまだ通る', send(array(
    'customer_name' => 'John Smith', 'address_detail' => $pitch)), true);

update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1',
    'spam_block_link' => '1', 'spam_require_ja' => '1')));
t('日本語チェックもオンにすると止まる', send(array(
    'customer_name' => 'John Smith', 'address_detail' => $pitch)), false);
t('★日本語のお客様は通ったまま', send(array(
    'customer_name' => '鈴木 花子', 'address_detail' => '丸の内1-2-3')), true);
/* ★ローマ字で名前を書く日本のお客様も巻き込む。ここが判断の分かれ目 */
t('※ローマ字で書く日本のお客様も止まる', send(array(
    'customer_name' => 'Taro Yamada', 'address_detail' => '丸の内1-2-3')), false);

update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1',
    'spam_words' => "SEO\nranking\ncrypto")));
t('NGワードで止まる', send(array(
    'customer_name' => 'John Smith', 'address_detail' => $pitch)), false);
t('関係ない日本語は通る', send(array('address_detail' => '丸の内1-2-3 ○○マンション101')), true);

echo "\n--- 5. 同じ相手からの連投 ---\n";
update_option(EAF_OPT, eaf_sanitize_options(array(
    'site_name' => '株式会社e.Amber', 'notify_email' => 'staff@example.test', 'notify_on' => '1')));
/* ★ここまでの成功分ですでに枠を使っている（＝制限が効いている証拠）。
     回数そのものを測るために、いったん数え直しから始める。
     子プロセスには REMOTE_ADDR が無いので 0.0.0.0 として数えられる。 */
delete_transient('eaf_rl_ip_' . md5('0.0.0.0'));
$ok = 0;
for ($i = 0; $i < 8; $i++) if (send(array('address_detail' => '丸の内' . $i))) $ok++;
t('同一IPは1時間に5件まで', $ok, 5);
t('6件目からは止まる',     send(array('address_detail' => '丸の内9')), false);

echo "\n--- 6. 自己診断 ---\n";
t('自己診断: 枠を戻せば受理される', $ok > 0, true);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
