<?php
/**
 * WordPress関数の最小スタブ（プラグイン通しテスト用）。
 * ★本番サイトでテストするとメールが実送信され、リードに実データが入るため厳禁。
 *   ここで完結させる。
 *
 * - オプション/リード/送信メールは state.json に永続化
 * - FakeWpdb は CREATE TABLE / ALTER TABLE をパースしてカラム定義を持ち、
 *   「未知のカラム」「長さ超過」で insert を false にする（MySQL strict mode 相当）
 *   → dbDelta の取りこぼしやカラム長超過によるリード消失を再現できる
 */

define('ABSPATH', __DIR__ . '/fakewp/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);

// 呼び出し側が先に指定していればそれを使う（unit.php は別ファイルで動かす）
if (empty($GLOBALS['FAKE_STATE_FILE'])) $GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/state.json';

function fake_state() {
    if (!isset($GLOBALS['FAKE_STATE'])) {
        $f = $GLOBALS['FAKE_STATE_FILE'];
        $GLOBALS['FAKE_STATE'] = file_exists($f)
            ? json_decode(file_get_contents($f), true)
            : array();
        if (!is_array($GLOBALS['FAKE_STATE'])) $GLOBALS['FAKE_STATE'] = array();
        $GLOBALS['FAKE_STATE'] += array(
            'options' => array(), 'transients' => array(), 'mails' => array(),
            'tables' => array(), 'rows' => array(), 'autoinc' => array(),
        );
    }
    return $GLOBALS['FAKE_STATE'];
}
function fake_save() {
    file_put_contents($GLOBALS['FAKE_STATE_FILE'], json_encode($GLOBALS['FAKE_STATE'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function fake_set($key, $val) { fake_state(); $GLOBALS['FAKE_STATE'][$key] = $val; fake_save(); }

/* ---------------- オプション ---------------- */
function get_option($k, $default = false) { $s = fake_state(); return array_key_exists($k, $s['options']) ? $s['options'][$k] : $default; }
function update_option($k, $v, $autoload = null) {
    fake_state();
    $GLOBALS['FAKE_STATE']['options'][$k] = $v;
    if ($autoload !== null) $GLOBALS['FAKE_STATE']['autoload'][$k] = $autoload;
    fake_save(); return true;
}
function add_option($k, $v, $deprecated = '', $autoload = 'yes') {
    fake_state();
    $GLOBALS['FAKE_STATE']['autoload'][$k] = $autoload;
    return update_option($k, $v);
}
/** テスト用: そのオプションが全ページで読まれる設定か */
function fake_autoload_of($k) { fake_state(); return $GLOBALS['FAKE_STATE']['autoload'][$k] ?? 'yes'; }
function delete_option($k) { fake_state(); unset($GLOBALS['FAKE_STATE']['options'][$k]); fake_save(); return true; }

/* ---------------- transient（レート制限で使う） ---------------- */
function get_transient($k) {
    $s = fake_state();
    if (!isset($s['transients'][$k])) return false;
    $t = $s['transients'][$k];
    if ($t['exp'] > 0 && $t['exp'] < time()) { delete_transient($k); return false; }
    return $t['val'];
}
function set_transient($k, $v, $ttl = 0) {
    fake_state();
    $GLOBALS['FAKE_STATE']['transients'][$k] = array('val' => $v, 'exp' => $ttl ? time() + $ttl : 0);
    fake_save(); return true;
}
function delete_transient($k) { fake_state(); unset($GLOBALS['FAKE_STATE']['transients'][$k]); fake_save(); return true; }
function get_site_transient($k) { return get_transient($k); }
function set_site_transient($k, $v, $t = 0) { return set_transient($k, $v, $t); }

/* ---------------- フック ---------------- */
$GLOBALS['FAKE_HOOKS'] = array();
function add_action($tag, $cb, $prio = 10, $args = 1) { $GLOBALS['FAKE_HOOKS'][$tag][] = $cb; }
function add_filter($tag, $cb, $prio = 10, $args = 1) { $GLOBALS['FAKE_HOOKS'][$tag][] = $cb; }
function do_action($tag) {
    $extra = array_slice(func_get_args(), 1);
    if (empty($GLOBALS['FAKE_HOOKS'][$tag])) return;
    foreach ($GLOBALS['FAKE_HOOKS'][$tag] as $cb) call_user_func_array($cb, $extra);
}
function has_action($tag) { return !empty($GLOBALS['FAKE_HOOKS'][$tag]); }
function apply_filters($tag, $value) {
    if (empty($GLOBALS['FAKE_HOOKS'][$tag])) return $value;
    foreach ($GLOBALS['FAKE_HOOKS'][$tag] as $cb) $value = call_user_func($cb, $value);
    return $value;
}
/* ---------------- スタイル/スクリプトのキュー ----------------
   本物と同じく「ハンドル単位で1回だけ」を再現する。
   積まれたインラインCSS/JSは fake_inline_style() / fake_inline_script() で取り出す。 */
$GLOBALS['FAKE_ASSETS'] = array('reg' => array(), 'enq' => array(), 'css' => '', 'js' => '');
function wp_register_style($h, $src = false, $deps = array(), $ver = null, $media = 'all') {
    $GLOBALS['FAKE_ASSETS']['reg']['style:' . $h] = true;
}
function wp_register_script($h, $src = false, $deps = array(), $ver = null, $footer = false) {
    $GLOBALS['FAKE_ASSETS']['reg']['script:' . $h] = true;
}
function wp_enqueue_style($h, $src = '', $deps = array(), $ver = null, $media = 'all') {
    $GLOBALS['FAKE_ASSETS']['enq']['style:' . $h] = true;
}
function wp_enqueue_script($h, $src = '', $deps = array(), $ver = null, $footer = false) {
    $GLOBALS['FAKE_ASSETS']['enq']['script:' . $h] = true;
}
function wp_style_is($h, $list = 'enqueued') {
    $k = ($list === 'registered') ? 'reg' : 'enq';
    return !empty($GLOBALS['FAKE_ASSETS'][$k]['style:' . $h]);
}
function wp_script_is($h, $list = 'enqueued') {
    $k = ($list === 'registered') ? 'reg' : 'enq';
    return !empty($GLOBALS['FAKE_ASSETS'][$k]['script:' . $h]);
}
function wp_add_inline_style($h, $css)  { $GLOBALS['FAKE_ASSETS']['css'] .= $css; return true; }
function wp_add_inline_script($h, $js)  { $GLOBALS['FAKE_ASSETS']['js']  .= $js;  return true; }
function wp_enqueue_media() {}
function fake_inline_style()  { return $GLOBALS['FAKE_ASSETS']['css']; }
function fake_inline_script() { return $GLOBALS['FAKE_ASSETS']['js']; }
function fake_assets_reset()  { $GLOBALS['FAKE_ASSETS'] = array('reg'=>array(),'enq'=>array(),'css'=>'','js'=>''); }

function register_activation_hook($file, $cb) { $GLOBALS['FAKE_ACTIVATE'][] = $cb; }
function add_shortcode($tag, $cb) { $GLOBALS['FAKE_SHORTCODES'][$tag] = $cb; }
function do_shortcode_call($tag, $atts = array()) { return call_user_func($GLOBALS['FAKE_SHORTCODES'][$tag], $atts); }
function shortcode_atts($pairs, $atts, $sc = '') {
    $out = array();
    foreach ($pairs as $k => $v) $out[$k] = array_key_exists($k, (array)$atts) ? $atts[$k] : $v;
    return $out;
}

/* ---------------- サニタイズ・エスケープ ---------------- */
function sanitize_text_field($s) { $s = strip_tags((string)$s); $s = preg_replace('/[\r\n\t]+/', ' ', $s); return trim($s); }
function sanitize_textarea_field($s) { return trim(strip_tags((string)$s)); }
function sanitize_email($s) { $s = trim((string)$s); return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : ''; }
function is_email($s) { return (bool)filter_var((string)$s, FILTER_VALIDATE_EMAIL); }
/**
 * WordPress の esc_url_raw / esc_url は「許可プロトコルのホワイトリスト」で、
 * javascript: や data: を落とす。スタブが trim だけだと危険なURLが素通りし、
 * 検査が通ってしまう（本番では落ちるのに、テストでは落ちない＝逆方向の嘘）。
 * 相対パス（/wp-content/... など）は本物と同じく許可する。
 */
function fake_clean_url($u) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $u, $m)) {
        $scheme = strtolower($m[1]);
        if (!in_array($scheme, array('http', 'https', 'mailto', 'tel'), true)) return '';
    }
    return $u;
}
function esc_url_raw($u) { return fake_clean_url($u); }
function esc_url($u) { return htmlspecialchars(fake_clean_url($u), ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function sanitize_hex_color($c) { $c = (string)$c; return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c) ? $c : ''; }
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_kses_post($s) { return $s; }
function absint($v) { return abs(intval($v)); }

/* ---------------- nonce / 権限 ---------------- */
function wp_create_nonce($a = '') { return 'nonce-' . md5($a); }
function wp_verify_nonce($n, $a = '') { return $n === 'nonce-' . md5($a) ? 1 : false; }
function check_ajax_referer($action, $field = '_wpnonce', $die = true) {
    $n = isset($_REQUEST[$field]) ? $_REQUEST[$field] : '';
    if (!wp_verify_nonce($n, $action)) {
        if ($die) { http_response_code(403); echo '-1'; exit; }
        return false;
    }
    return 1;
}
function check_admin_referer($action = '-1', $q = '_wpnonce') {
    // 既定は素通り（既存テストの互換）。$GLOBALS['FAKE_STRICT_NONCE'] を立てた時だけ本物同様に検査する
    if (empty($GLOBALS['FAKE_STRICT_NONCE'])) return 1;
    $n = isset($_REQUEST[$q]) ? $_REQUEST[$q] : '';
    if (!wp_verify_nonce($n, $action)) wp_die('リンクの有効期限が切れています。');
    return 1;
}
function wp_nonce_url($url, $a = -1) { return $url . (strpos($url, '?') !== false ? '&' : '?') . '_wpnonce=' . wp_create_nonce($a); }
/**
 * 既定は管理者（既存テストの互換）。
 * $GLOBALS['FAKE_CAPS'] に配列を入れると、その権限だけを持つ利用者として振る舞う。
 * 例: array()           … 未ログインの訪問者
 *     array('read')     … 購読者
 */
function current_user_can($c) {
    if (!isset($GLOBALS['FAKE_CAPS'])) return true;
    return in_array($c, (array) $GLOBALS['FAKE_CAPS'], true);
}
function wp_get_current_user() { return (object)array('user_email' => 'admin@example.test'); }
function is_admin() { return !empty($GLOBALS['FAKE_IS_ADMIN']); }
/**
 * 本物は処理を打ち切る。テストで「打ち切られたこと」を確かめたいので、
 * $GLOBALS['FAKE_WP_DIE_THROW'] を立てた時は例外にして呼び出し側で捕まえられるようにする。
 */
class FakeWpDie extends Exception {}
function wp_die($m = '') {
    if (!empty($GLOBALS['FAKE_WP_DIE_THROW'])) throw new FakeWpDie(is_string($m) ? $m : '');
    echo '[wp_die] ' . $m; exit;
}
function admin_url($p = '') { return '/wp-admin/' . ltrim($p, '/'); }
/** テスト用: $GLOBALS['FAKE_HOME_URL'] で http/https を切り替えられる */
function home_url($p = '') { return ($GLOBALS['FAKE_HOME_URL'] ?? 'https://example.test') . $p; }
function site_url($p = '') { return home_url($p); }
function plugin_basename($f) { return basename(dirname($f)) . '/' . basename($f); }
function plugin_dir_path($f) { return dirname($f) . '/'; }
function wp_safe_redirect($u) { header('Location: ' . $u); echo "[redirect] $u"; }
function nocache_headers() {}
function current_time($t = 'mysql') { return date('Y-m-d H:i:s'); }

/* ---------------- 固定ページ（査定ページの自動作成用） ---------------- */
function wp_insert_post($args) {
    fake_state();
    $s = $GLOBALS['FAKE_STATE'];
    $posts = isset($s['posts']) ? $s['posts'] : array();
    $id = 100 + count($posts) + 1;
    $posts[$id] = array(
        'ID' => $id,
        'post_title'   => $args['post_title'] ?? '',
        'post_name'    => $args['post_name'] ?? '',
        'post_status'  => $args['post_status'] ?? 'draft',
        'post_type'    => $args['post_type'] ?? 'post',
        'post_content' => $args['post_content'] ?? '',
    );
    fake_set('posts', $posts);
    return $id;
}
function get_page_by_path($slug, $output = OBJECT, $type = 'page') {
    $s = fake_state();
    foreach ((isset($s['posts']) ? $s['posts'] : array()) as $p) {
        if ($p['post_name'] === $slug && $p['post_type'] === $type) return (object)$p;
    }
    return null;
}
function get_post_status($id) {
    $s = fake_state();
    return isset($s['posts'][$id]) ? $s['posts'][$id]['post_status'] : false;
}
function get_permalink($id) {
    $s = fake_state();
    return isset($s['posts'][$id]) ? '/' . $s['posts'][$id]['post_name'] . '/' : false;
}
function get_edit_post_link($id) { return '/wp-admin/post.php?post=' . (int)$id . '&action=edit'; }
function wp_dropdown_pages($args = array()) {
    $s = fake_state();
    echo '<select name="' . esc_attr($args['name'] ?? 'page_id') . '">';
    echo '<option value="0">' . esc_html($args['show_option_none'] ?? '') . '</option>';
    foreach ((isset($s['posts']) ? $s['posts'] : array()) as $p) {
        if ($p['post_type'] !== 'page') continue;
        $sel = ((int)($args['selected'] ?? 0) === (int)$p['ID']) ? ' selected' : '';
        echo '<option value="' . (int)$p['ID'] . '"' . $sel . '>' . esc_html($p['post_title']) . '</option>';
    }
    echo '</select>';
}
/** テスト用: 固定ページの状態を変える */
function fake_set_post_status($id, $status) {
    fake_state();
    if (isset($GLOBALS['FAKE_STATE']['posts'][$id])) {
        $GLOBALS['FAKE_STATE']['posts'][$id]['post_status'] = $status;
        fake_save();
    }
}

/* ---------------- 更新チェック ---------------- */
function delete_site_transient($k) { return delete_transient($k); }
function wp_update_plugins() { $GLOBALS['FAKE_UPDATE_RAN'] = true; }

/* ---------------- 管理画面まわり ---------------- */
function add_menu_page() {}
function add_submenu_page() {}
function register_setting() {}
function settings_fields($g) { echo '<input type="hidden" name="option_page" value="' . esc_attr($g) . '">'; }
function submit_button($t = '変更を保存') { echo '<p><button type="submit" class="button button-primary">' . esc_html($t) . '</button></p>'; }
function checked($a, $b = true, $echo = true) { $r = ((string)$a === (string)$b || ($b === true && $a)) ? ' checked' : ''; if ($echo) echo $r; return $r; }
function selected($a, $b = true, $echo = true) { $r = ((string)$a === (string)$b) ? ' selected' : ''; if ($echo) echo $r; return $r; }

/* ---------------- メール（実送信せず捕捉） ---------------- */
function wp_mail($to, $subject, $body, $headers = array()) {
    fake_state();
    $GLOBALS['FAKE_STATE']['mails'][] = array(
        'to' => $to, 'subject' => $subject, 'body' => $body,
        'headers' => (array)$headers, 'at' => date('Y-m-d H:i:s'),
    );
    fake_save();
    return !empty($GLOBALS['FAKE_MAIL_OK']) || !isset($GLOBALS['FAKE_MAIL_OK']);
}

/* ---------------- 疑似 $wpdb ---------------- */
class FakeWpdb {
    public $prefix = 'wp_';
    public $last_error = '';
    /** テーブル定義: table => array(col => array('type'=>..., 'len'=>int|null)) */
    private function tables() { $s = fake_state(); return $s['tables']; }
    private function put_tables($t) { fake_set('tables', $t); }
    private function rows() { $s = fake_state(); return $s['rows']; }
    private function put_rows($r) { fake_set('rows', $r); }

    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }

    public function prepare($q, ...$args) {
        $q = str_replace(array('%s', '%d'), array("'%s'", '%d'), $q);
        foreach ($args as $a) {
            $q = preg_replace('/%s|%d/', is_numeric($a) ? $a : addslashes($a), $q, 1);
        }
        return $q;
    }

    /** CREATE TABLE をパースしてカラム定義を作る（dbDelta 相当） */
    public function create_from_sql($sql) {
        if (!preg_match('/CREATE TABLE\s+(\S+)\s*\((.*)\)\s*[^)]*;?\s*$/is', $sql, $m)) return;
        $table = trim($m[1], '`');
        $body  = $m[2];
        $defs = $this->tables();
        $cols = isset($defs[$table]) ? $defs[$table] : array();
        foreach (preg_split('/,\s*\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(PRIMARY|KEY|UNIQUE|INDEX)/i', $line)) continue;
            if (!preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([A-Z]+)(\((\d+)\))?/i', $line, $c)) continue;
            $name = $c[1];
            if (isset($cols[$name])) continue;   // 既存カラムは変更しない（dbDeltaは型変更もするが今回は不要）
            $cols[$name] = array('type' => strtoupper($c[2]), 'len' => isset($c[4]) ? (int)$c[4] : null);
        }
        $defs[$table] = $cols;
        $this->put_tables($defs);
    }

    public function query($sql) {
        // ALTER TABLE `x` ADD COLUMN `y` VARCHAR(100) NULL
        if (preg_match('/ALTER TABLE\s+`?([a-zA-Z0-9_]+)`?\s+ADD COLUMN\s+`?([a-zA-Z0-9_]+)`?\s+([A-Z]+)(\((\d+)\))?/i', $sql, $m)) {
            $defs = $this->tables();
            $t = $m[1];
            if (!isset($defs[$t])) return false;
            $defs[$t][$m[2]] = array('type' => strtoupper($m[3]), 'len' => isset($m[5]) ? (int)$m[5] : null);
            $this->put_tables($defs);
            return 1;
        }
        return true;
    }

    public function get_var($sql) {
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/i", $sql, $m)) {
            $defs = $this->tables();
            return isset($defs[$m[1]]) ? $m[1] : null;
        }
        if (preg_match('/SELECT COUNT\(\*\) FROM (\S+)/i', $sql, $m)) {
            $rows = $this->rows();
            $t = trim($m[1], '`');
            return isset($rows[$t]) ? count($rows[$t]) : 0;
        }
        return null;
    }

    public function get_col($sql, $i = 0) {
        if (preg_match('/SHOW COLUMNS FROM `?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
            $defs = $this->tables();
            return isset($defs[$m[1]]) ? array_keys($defs[$m[1]]) : array();
        }
        return array();
    }

    public function get_results($sql, $mode = OBJECT) {
        if (!preg_match('/FROM\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) return array();
        $rows = $this->rows();
        $t = $m[1];
        $list = isset($rows[$t]) ? $rows[$t] : array();
        usort($list, function ($a, $b) { return $b['id'] - $a['id']; });   // ORDER BY id DESC
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $lm)) $list = array_slice($list, 0, (int)$lm[1]);
        return $mode === ARRAY_A ? $list : array_map(function ($r) { return (object)$r; }, $list);
    }

    /** ★本番同様に「未知カラム」「長さ超過」で失敗させる */
    public function insert($table, $data, $format = null) {
        $defs = $this->tables();
        if (!isset($defs[$table])) { $this->last_error = "Table '$table' doesn't exist"; return false; }
        $cols = $defs[$table];
        foreach ($data as $k => $v) {
            if (!isset($cols[$k])) { $this->last_error = "Unknown column '$k' in 'field list'"; return false; }
            $len = $cols[$k]['len'];
            // MySQL の VARCHAR(n) は「文字数」。バイト数で数えると日本語で誤判定する
            $slen = function_exists('mb_strlen')
                ? mb_strlen((string)$v)
                : (preg_match_all('/./us', (string)$v) ?: strlen((string)$v));
            if ($len !== null && $cols[$k]['type'] === 'VARCHAR' && $slen > $len) {
                $this->last_error = "Data too long for column '$k' at row 1";
                return false;
            }
        }
        $rows = $this->rows();
        $s = fake_state();
        $next = isset($s['autoinc'][$table]) ? $s['autoinc'][$table] + 1 : 1;
        $GLOBALS['FAKE_STATE']['autoinc'][$table] = $next;
        $row = array('id' => $next);
        foreach ($cols as $c => $d) $row[$c] = array_key_exists($c, $data) ? $data[$c] : null;
        $row['id'] = $next;
        $rows[$table][] = $row;
        $this->put_rows($rows);
        $this->last_error = '';
        return 1;
    }

    public function delete($table, $where) {
        $rows = $this->rows();
        if (!isset($rows[$table])) return 0;
        $n = 0;
        foreach ($rows[$table] as $i => $r) {
            $hit = true;
            foreach ($where as $k => $v) if (!isset($r[$k]) || (string)$r[$k] !== (string)$v) $hit = false;
            if ($hit) { unset($rows[$table][$i]); $n++; }
        }
        $rows[$table] = array_values($rows[$table]);
        $this->put_rows($rows);
        return $n;
    }
}
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
$GLOBALS['wpdb'] = new FakeWpdb();

function dbDelta($sql) { $GLOBALS['wpdb']->create_from_sql($sql); }

function wp_send_json($data) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($data);
    exit;
}
if (!function_exists('wp_remote_get')) {
function wp_remote_get($url, $args = array()) { return new WP_Error('no-net', 'disabled in test'); }
}
function is_wp_error($t) { return ($t instanceof WP_Error); }
class WP_Error { public $code; public $msg; function __construct($c = '', $m = '') { $this->code = $c; $this->msg = $m; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($r) { return 0; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($r) { return ''; } }
