<?php
/**
 * php -S 用ルーター（プラグイン通しテスト）。
 *   php -S 127.0.0.1:4491 router.php
 *
 *   /                    フォーム（?design=compact|card で切替）
 *   /admin-ajax.php      本物のAJAXハンドラをディスパッチ
 *   /settings            設定画面のHTML（描画が壊れていないか確認）
 *   /leads               申込一覧のHTML
 *   /__state             保存されたリード・送信メール・オプションをJSONで確認
 *   /__reset             状態をリセット
 *   /__setopt?k=..&v=..  オプションを1件設定（モード切替のテスト用）
 *   /twice               同一ページに2つフォームを設置（id重複の確認）
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/wp_stub.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 静的ファイルは php -S に任せる
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // php -S は .svg の Content-Type を返さないため、画像として扱われない。ここで補う
    if (strtolower(substr($uri, -4)) === '.svg') {
        header('Content-Type: image/svg+xml');
        readfile(__DIR__ . $uri);
        exit;
    }
    return false;
}

if ($uri === '/__reset') {
    @unlink(__DIR__ . '/state.json');
    echo 'reset';
    exit;
}

$GLOBALS['FAKE_IS_ADMIN'] = in_array($uri, array('/settings', '/leads', '/save'), true);
require_once dirname(__DIR__) . '/eamber-form/eamber-form.php';

// 有効化フック（テーブル作成）。?noactivate=1 を付けると
// 「既にインストール済みのサイトが自動更新で新版になった」状況（plugins_loaded だけが走る）を再現できる
if (empty($_GET['noactivate'])) {
    foreach ($GLOBALS['FAKE_ACTIVATE'] as $cb) call_user_func($cb);
}
do_action('plugins_loaded');

if ($uri === '/__oldtable') {
    // 旧バージョン相当の、お名前・電話などが無いテーブル定義に差し替える
    fake_state();
    $GLOBALS['FAKE_STATE']['tables'] = array('wp_eamber_form_leads' => array(
        'id'               => array('type' => 'BIGINT',   'len' => 20),
        'created_at'       => array('type' => 'DATETIME', 'len' => null),
        'email'            => array('type' => 'VARCHAR',  'len' => 191),
        'marketing_opt_in' => array('type' => 'TINYINT',  'len' => 1),
    ));
    $GLOBALS['FAKE_STATE']['options']['eaf_db_ver'] = '0.9.0';
    fake_save();
    echo 'old table set';
    exit;
}

if ($uri === '/__state') {
    header('Content-Type: application/json; charset=utf-8');
    $s = fake_state();
    echo json_encode(array(
        'options' => $s['options'],
        'tables'  => array_map('array_keys', $s['tables']),
        'rows'    => $s['rows'],
        'mails'   => $s['mails'],
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($uri === '/__seed') {
    // 運営者情報など、公開前に埋めるべき設定を投入（日本語をURL経由で渡さずに済む）
    $o = get_option(EAF_OPT, array());
    if (!is_array($o)) $o = array();
    $o = array_merge($o, array(
        'site_name'        => 'ミカタ不動産査定',
        'operator_name'    => 'ミカタ株式会社',
        'operator_address' => '岡山県岡山市北区○○1-2-3',
        'operator_contact' => '086-000-0000 / info@example.test',
        'notify_email'     => 'staff@example.test',
        'notify_on'        => '1',
        'privacy_url'      => 'https://example.test/privacy',
        'terms_url'        => 'https://example.test/terms',
        'lead_text'        => "担当者が実際に物件を確認し、根拠のある価格をご提示します。\nまずはお気軽にお申し込みください。",
    ));
    if (isset($_GET['third_party'])) {
        $o['third_party'] = $_GET['third_party'] === '1' ? '1' : '0';
        $o['third_party_name'] = '当社が提携する不動産会社（お住まいの地域を担当する1〜3社）';
    }
    update_option(EAF_OPT, $o);
    echo 'seeded';
    exit;
}

if ($uri === '/__setopt') {
    $o = get_option(EAF_OPT, array());
    if (!is_array($o)) $o = array();
    $k = $_GET['k'] ?? ''; $v = $_GET['v'] ?? '';
    if ($k !== '') { $o[$k] = $v; update_option(EAF_OPT, $o); }
    echo 'ok: ' . $k . '=' . $v;
    exit;
}

if ($uri === '/__setopts') {
    // JSONでまとめて設定（?json={"operator_name":"..."} ）
    $o = get_option(EAF_OPT, array());
    if (!is_array($o)) $o = array();
    $j = json_decode($_GET['json'] ?? '{}', true);
    if (is_array($j)) { foreach ($j as $k => $v) $o[$k] = $v; update_option(EAF_OPT, $o); }
    echo 'ok';
    exit;
}

if ($uri === '/admin-ajax.php' || $uri === '/wp-admin/admin-ajax.php') {
    $action = $_REQUEST['action'] ?? '';
    $hook = 'wp_ajax_nopriv_' . $action;
    if (!has_action($hook)) { echo '0'; exit; }
    do_action($hook);
    exit;
}

if ($uri === '/settings') {
    echo '<!doctype html><meta charset="utf-8"><title>設定</title><body>';
    do_action('admin_notices');
    eaf_settings_page();
    echo '</body>';
    exit;
}

if ($uri === '/leads') {
    echo '<!doctype html><meta charset="utf-8"><title>一覧</title><body>';
    eaf_leads_page();
    echo '</body>';
    exit;
}

if ($uri === '/delete') { $_GET['id'] = $_GET['id'] ?? 0; eaf_delete_lead(); exit; }
if ($uri === '/testmail') { eaf_test_mail(); exit; }

if ($uri === '/csv') {
    eaf_export_leads();
    exit;
}

// フォーム表示（ショートコード属性はクエリでそのまま渡せる: ?design=teaser&url=/&fields=ptype,address）
$design = $_GET['design'] ?? 'default';
$twice  = ($uri === '/twice');
$sc_atts = array('design' => $design);
foreach (array('url','title','subtitle','note','fields','logo','badge','steps','button','width','tags') as $k) {
    if (isset($_GET[$k])) $sc_atts[$k] = $_GET[$k];
}
echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<title>訪問査定申込テスト</title><body style="font-family:sans-serif;max-width:900px;margin:20px auto;padding:0 16px">';
if ($uri === '/multi') {
    // 1ページに「横長ティザー」「縦ティザー」「本フォーム」を同居させる
    echo '<h2>横長ティザー</h2>' . do_shortcode_call('eamber_form', array('design' => 'teaser', 'url' => '/', 'width' => '820'));
    echo '<h2>縦ティザー</h2>'   . do_shortcode_call('eamber_form', array('design' => 'teaser-v', 'url' => '/'));
    echo '<h2>本フォーム</h2>'   . do_shortcode_call('eamber_form', array());
    echo '<h2>本フォーム（2つ目・compact）</h2>' . do_shortcode_call('eamber_form', array('design' => 'compact'));
    echo '</body>';
    exit;
}
if ($uri === '/lp') {
    // LPのように、同じティザーを1ページへいくつも置いたときの検証
    $n = max(2, min(8, (int)($_GET['n'] ?? 5)));
    for ($i = 1; $i <= $n; $i++) {
        echo '<h2>ティザー ' . $i . '</h2>';
        echo do_shortcode_call('eamber_form', array('design' => 'teaser', 'url' => '/', 'width' => '820'));
        echo '<p style="height:40px"></p>';
    }
    echo '</body>';
    exit;
}
echo do_shortcode_call('eamber_form', $sc_atts);
if ($twice) echo '<hr>' . do_shortcode_call('eamber_form', $sc_atts);
echo '</body>';
