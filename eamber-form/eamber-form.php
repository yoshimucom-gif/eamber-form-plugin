<?php
/**
 * Plugin Name: e.Amber お問い合わせフォーム
 * Description: 電気工事の問い合わせフォーム。工事内容を選ぶと、その内容に合わせた質問に切り替わるステップ型フォームです。受付内容はDBに保存され、受付完了メールを自動返信＋担当者に通知します。入力項目は1つずつ「必須／任意／非表示」を選べます。ショートコード [eamber_form] をページに貼るだけ。
 * Version: 1.0.2
 * Author: 株式会社Keys
 * License: GPLv2 or later
 * Text Domain: eamber-form
 *
 * ★法的注意:
 *   - 氏名・電話番号という個人情報を取得するため、利用目的の明示（個情法21条）と、
 *     提携先へ渡す場合は第三者提供の同意（個情法27条）が必須。設定でON/OFFできる。
 *   公開前に弁護士等の確認を推奨。
 */

if (!defined('ABSPATH')) exit; // 直接アクセス禁止

define('EAF_VER', '1.0.2');
define('EAF_OPT', 'eamber_form_options');

/**
 * 自動更新の置き場（update.json の URL）。
 * 新バージョンを置くと WP管理画面に「更新可能」バッジが出てワンクリック更新できる。
 * ※ 空なら自動更新は無効（手動アップロードでの運用は可能）。
 */
define('EAF_UPDATE_URL', 'https://raw.githubusercontent.com/yoshimucom-gif/eamber-form-plugin/main/update.json');

/* 更新チェッカー（URL未設定なら無効）
   ★is_admin() で囲まないこと。WordPressの自動更新は WP-Cron（管理画面外）で走るため、
     管理画面限定にすると「自動更新を有効化」をONにしても更新が入らない。 */
if (is_admin() || (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)) {
    require_once __DIR__ . '/includes/plugin-updater.php';
    new EAF_Updater(__FILE__, EAF_UPDATE_URL);
}

/* =========================================================================
 * 1. 入力項目のスキーマ（このプラグインの心臓部）
 *
 *    すべての入力項目は「必須(req) / 任意(opt) / 非表示(off)」を設定画面から
 *    1つずつ切り替えられる。'def' はその初期値。
 *    'col' … 保存先のDBカラム名（null なら「詳細」テキストにまとめて保存）
 * ======================================================================= */

/** お客様のご連絡先 */
function eaf_customer_fields() {
    return array(
        array('key'=>'name',         'label'=>'お名前',              'type'=>'text',   'col'=>'name',          'len'=>100, 'def'=>'req', 'ph'=>'例：山田 太郎'),
        array('key'=>'kana',         'label'=>'フリガナ',            'type'=>'text',   'col'=>'kana',          'len'=>100, 'def'=>'off', 'ph'=>'例：ヤマダ タロウ'),
        array('key'=>'tel',          'label'=>'電話番号',            'type'=>'tel',    'col'=>'tel',           'len'=>50,  'def'=>'req', 'ph'=>'例：090-1234-5678'),
        array('key'=>'contact_time', 'label'=>'ご連絡しやすい時間帯', 'type'=>'select', 'col'=>'contact_time',  'len'=>50,  'def'=>'off', 'opts'=>'contact_time'),
        array('key'=>'contact_way',  'label'=>'ご希望の連絡方法',     'type'=>'select', 'col'=>null,            'len'=>50,  'def'=>'off', 'opts'=>'contact_way'),
    );
}

/** ご状況（担当者が優先順位を付けるための情報）
 *  ★市町村はここに置かない。フォームの基幹項目（address）として最初のステップで聞く。
 *  ★既定はすべて非表示。フォームに並ぶのは必須項目だけにして、
 *    聞きたいことが出てきたら管理画面で1つずつ足す（足すほど反響は減る）。 */
function eaf_situation_fields() {
    return array(
        array('key'=>'building',   'label'=>'建物の種類',            'type'=>'select', 'col'=>'building',  'len'=>50,  'def'=>'off', 'opts'=>'building'),
        array('key'=>'ownership',  'label'=>'持ち家か賃貸か',        'type'=>'select', 'col'=>'ownership', 'len'=>50,  'def'=>'off', 'opts'=>'ownership'),
        array('key'=>'since',      'label'=>'いつからの症状ですか',   'type'=>'select', 'col'=>null,        'len'=>50,  'def'=>'off', 'opts'=>'since'),
        array('key'=>'timing',     'label'=>'ご希望の時期',          'type'=>'select', 'col'=>'timing',    'len'=>50,  'def'=>'off', 'opts'=>'timing'),
        array('key'=>'detail',     'label'=>'症状・ご希望',          'type'=>'textarea','col'=>'detail',   'len'=>2000,'def'=>'off', 'ph'=>'例：2階の部屋のブレーカーだけ、エアコンを付けると落ちます'),
    );
}

/** 工事内容ごとの入力項目（内容によって聞くことが変わる）
 *  ★既定で出すのは各内容の「1問だけ」。それ以外は非表示にしてある。
 *    ここを増やすほど反響は減るので、足すときは1つずつ試すこと。 */
function eaf_property_fields() {
    return array(
        'aircon' => array(
            array('key'=>'ac_work',    'label'=>'ご希望の作業',        'type'=>'select', 'def'=>'req', 'opts'=>'ac_work'),
            array('key'=>'ac_floor',   'label'=>'設置する階',          'type'=>'select', 'def'=>'off', 'opts'=>'ac_floor'),
            array('key'=>'ac_outlet',  'label'=>'専用コンセントの有無', 'type'=>'select', 'def'=>'off', 'opts'=>'yesno_unknown'),
            array('key'=>'ac_body',    'label'=>'本体のご用意',        'type'=>'select', 'def'=>'off', 'opts'=>'body_ready'),
            array('key'=>'ac_model',   'label'=>'型番（分かれば）',     'type'=>'text',   'def'=>'off', 'ph'=>'例：AN-C223SE'),
        ),
        'breaker' => array(
            array('key'=>'br_symptom', 'label'=>'症状',                'type'=>'select', 'def'=>'req', 'opts'=>'br_symptom'),
            array('key'=>'br_scope',   'label'=>'止まっている範囲',     'type'=>'select', 'def'=>'off', 'opts'=>'br_scope'),
            array('key'=>'br_smell',   'label'=>'焦げたにおいの有無',   'type'=>'select', 'def'=>'off', 'opts'=>'yesno_unknown'),
            array('key'=>'br_wire',    'label'=>'単相2線式／3線式',     'type'=>'select', 'def'=>'off', 'opts'=>'wire_type'),
            array('key'=>'br_age',     'label'=>'分電盤の設置年（西暦）','type'=>'number','def'=>'off', 'ph'=>'例：1995'),
        ),
        'intercom' => array(
            array('key'=>'ic_symptom', 'label'=>'症状',                'type'=>'select', 'def'=>'req', 'opts'=>'ic_symptom'),
            array('key'=>'ic_type',    'label'=>'いまの機種',          'type'=>'select', 'def'=>'off', 'opts'=>'ic_type'),
            array('key'=>'ic_want',    'label'=>'ご希望の機種',        'type'=>'select', 'def'=>'off', 'opts'=>'ic_want'),
            array('key'=>'ic_year',    'label'=>'建物の築年（西暦）',   'type'=>'number', 'def'=>'off', 'ph'=>'例：2005'),
        ),
        'outlet' => array(
            array('key'=>'ol_work',    'label'=>'ご希望の作業',        'type'=>'select', 'def'=>'req', 'opts'=>'ol_work'),
            array('key'=>'ol_count',   'label'=>'箇所数',              'type'=>'number', 'def'=>'off', 'ph'=>'例：2'),
            array('key'=>'ol_place',   'label'=>'設置場所',            'type'=>'select', 'def'=>'off', 'opts'=>'ol_place'),
            array('key'=>'ol_volt',    'label'=>'100Vか200Vか',        'type'=>'select', 'def'=>'off', 'opts'=>'volt'),
        ),
        'light' => array(
            array('key'=>'lt_work',    'label'=>'ご希望の作業',        'type'=>'select', 'def'=>'req', 'opts'=>'lt_work'),
            array('key'=>'lt_count',   'label'=>'台数',                'type'=>'number', 'def'=>'off', 'ph'=>'例：4'),
            array('key'=>'lt_ceiling', 'label'=>'引掛シーリングの有無', 'type'=>'select', 'def'=>'off', 'opts'=>'yesno_unknown'),
            array('key'=>'lt_height',  'label'=>'天井が高い場所か',     'type'=>'select', 'def'=>'off', 'opts'=>'yesno_unknown'),
        ),
        'fan' => array(
            array('key'=>'fn_place',   'label'=>'設置場所',            'type'=>'select', 'def'=>'req', 'opts'=>'fn_place'),
            array('key'=>'fn_symptom', 'label'=>'症状',                'type'=>'select', 'def'=>'off', 'opts'=>'fn_symptom'),
            array('key'=>'fn_type',    'label'=>'種類',                'type'=>'select', 'def'=>'off', 'opts'=>'fn_type'),
        ),
        'business' => array(
            array('key'=>'bz_kind',    'label'=>'建物の用途',          'type'=>'select', 'def'=>'req', 'opts'=>'bz_kind'),
            array('key'=>'bz_work',    'label'=>'ご検討の工事',        'type'=>'select', 'def'=>'off', 'opts'=>'bz_work'),
            array('key'=>'bz_stop',    'label'=>'停電させられる時間帯', 'type'=>'select', 'def'=>'off', 'opts'=>'bz_stop'),
            array('key'=>'bz_tenant',  'label'=>'テナント入居か自社物件か','type'=>'select','def'=>'off','opts'=>'bz_tenant'),
        ),
        /* ★その他だけは自由記述を必須にする。ここを非表示にすると
           「その他・分からない」を選んだ人が何も伝えられないフォームになる。 */
        'other' => array(
            array('key'=>'ot_note',    'label'=>'お困りの内容',        'type'=>'textarea','def'=>'req', 'ph'=>'例：何が起きているか分かりませんが、時々部屋の電気が消えます'),
        ),
    );
}

/**
 * ティザー（記事内などに置く短い入口フォーム）に出せる項目。
 *
 * ★お名前・フリガナ・電話番号・メールは意図的に含めない。
 *   ティザーは同意チェックの前段であり、個人情報を受け取る画面ではないため。
 *   （個人情報を受け取るのは、利用目的の明示と同意チェックがある本フォームだけにする）
 */
function eaf_teaser_fields() {
    return array(
        'ptype'   => array('label' => 'お困りの内容',   'type' => 'ptype'),
        'address' => array('label' => '市町村',        'type' => 'select', 'opts' => 'city'),
        'timing'  => array('label' => 'ご希望の時期',   'type' => 'select', 'opts' => 'timing'),
    );
}

/** 「見積り無料, 出張費込み, 相談だけOK」のような文字列をタグの配列に。
 *  半角/全角のカンマ、読点、縦棒のどれでも区切れるようにする（書き方で迷わせない）。 */
function eaf_split_tags($raw) {
    $parts = preg_split('/[,，、|｜]+/u', (string)$raw);   // 半角/全角カンマ・読点・縦棒
    if (!is_array($parts)) return array();
    $out = array();
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
}

/** fields="ptype,address" を検証済みの順序付きリストに（既定は工事内容＋市町村） */
function eaf_parse_teaser_fields($raw) {
    $known = eaf_teaser_fields();
    $out = array();
    foreach (explode(',', (string)$raw) as $k) {
        $k = trim($k);
        if ($k !== '' && isset($known[$k]) && !in_array($k, $out, true)) $out[] = $k;
    }
    return $out ? $out : array('ptype', 'address');
}

/**
 * 属性の間の半角スペースが抜けていても読み取れるようにする。
 *
 * [eamber_form design="teaser" url="/○○○/form/"width="640"]
 *                                          ↑ ここにスペースが無い
 * WordPressは属性を空白区切りで読むため、この塊を属性として認識できず、
 * 「値のない項目」として番号付きで渡してくる。結果 url が空になり、
 * 「urlを指定してください」と出るのに書いてある、という分かりにくい状態になる。
 * よくある書き間違いなので、ここで分解して拾い、管理者にだけ直し方を知らせる。
 */
function eaf_unglue_atts($atts) {
    if (!is_array($atts)) return $atts;
    $out = array(); $fixed = false;
    foreach ($atts as $k => $v) {
        if (is_int($k) && is_string($v)
            && preg_match_all('/([\w-]+)\s*=\s*"([^"]*)"/', $v, $m, PREG_SET_ORDER)
            && count($m) >= 2) {
            foreach ($m as $one) $out[strtolower($one[1])] = $one[2];
            $fixed = true;
            continue;
        }
        $out[$k] = $v;
    }
    if ($fixed) $out['eaf_glued'] = '1';
    return $out;
}

/** ティザーの項目キー → 本フォームでの入力欄名（引き継ぎで同じ欄に流し込むため） */
function eaf_teaser_form_name($key) {
    return in_array($key, array('ptype', 'address'), true) ? $key : 'situation_' . $key;
}

/** 設定・検証で使うグループ一覧（キーはモード保存とPOSTキーの接頭辞） */
function eaf_all_groups() {
    $g = array(
        'customer'  => eaf_customer_fields(),
        'situation' => eaf_situation_fields(),
    );
    foreach (eaf_property_fields() as $pt => $flds) $g['prop_' . $pt] = $flds;
    return $g;
}

/** セレクトの選択肢 */
function eaf_opt_list($key) {
    switch ($key) {
        /* ── 共通 ───────────────────────────────────── */
        case 'city': return array(
            '甲府市','富士吉田市','都留市','山梨市','大月市','韮崎市','南アルプス市',
            '北杜市','甲斐市','笛吹市','上野原市','甲州市','中央市',
            '市川三郷町','早川町','身延町','南部町','富士川町','昭和町',
            '道志村','西桂町','忍野村','山中湖村','鳴沢村','富士河口湖町',
            '小菅村','丹波山村','山梨県外');
        case 'building':     return array('戸建て','アパート・マンション','店舗・事務所','工場・倉庫','その他');
        case 'ownership':    return array('持ち家','賃貸','分からない');
        case 'since':        return array('今日','数日前から','1か月以上前から','これから工事したい');
        case 'timing':       return array('できるだけ早く','今週中','来週以降','未定・相談したい');
        case 'contact_time': return array('指定なし','午前（9〜12時）','午後（12〜17時）','夕方以降（17〜18時）','平日のみ希望','土日のみ希望');
        case 'contact_way':  return array('電話','メール','どちらでも');
        case 'yesno_unknown':return array('ある','ない','分からない');
        case 'volt':         return array('100V','200V','分からない');
        case 'wire_type':    return array('単相2線式','単相3線式','分からない');

        /* ── エアコン ───────────────────────────────── */
        case 'ac_work':   return array('新しく取り付けたい','入れ替えたい','移設したい','取り外したい','効かない・水漏れ（修理）');
        case 'ac_floor':  return array('1階','2階','3階以上','分からない');
        case 'body_ready':return array('自分で用意済み・購入予定','e.Amberに手配してほしい','未定');

        /* ── ブレーカー・漏電・分電盤 ───────────────── */
        case 'br_symptom':return array('落ちて戻らない','何度も落ちる','焦げたにおいがする','電気代が急に上がった','分電盤を交換したい','契約アンペアを上げたい');
        case 'br_scope':  return array('家全体','特定の部屋だけ','特定の機器だけ','分からない');

        /* ── インターホン ───────────────────────────── */
        case 'ic_symptom':return array('鳴らない','映らない','勝手に鳴る','古いので替えたい','新しく付けたい');
        case 'ic_type':   return array('音声のみ','カメラ付き（モニターあり）','チャイムのみ','分からない');
        case 'ic_want':   return array('同等品でよい','カメラ付きにしたい','スマホ連動にしたい','相談したい');

        /* ── コンセント・スイッチ ───────────────────── */
        case 'ol_work':   return array('増設したい','交換したい','焦げ臭い・熱い','スイッチの調子が悪い','屋外に付けたい','EV充電用を付けたい');
        case 'ol_place':  return array('居室','台所','洗面・浴室','屋外','駐車場','その他');

        /* ── 照明 ───────────────────────────────────── */
        case 'lt_work':   return array('交換したい','LED化したい','チカチカする・点かない','新しく付けたい');

        /* ── 換気扇 ─────────────────────────────────── */
        case 'fn_place':  return array('台所','浴室','トイレ','洗面所','その他');
        case 'fn_symptom':return array('動かない','異音がする','風が弱い','古いので替えたい');
        case 'fn_type':   return array('プロペラ式','シロッコ（レンジフード）','天井埋込型','分からない');

        /* ── 法人 ───────────────────────────────────── */
        case 'bz_kind':   return array('店舗','事務所','工場','倉庫','アパート・マンション（オーナー）','その他');
        case 'bz_work':   return array('業務用エアコン','LED化','キュービクル更新','LAN配線','新築','改装・原状回復','その他');
        case 'bz_stop':   return array('営業時間中でも可','営業時間外のみ','休業日のみ','止められない','相談したい');
        case 'bz_tenant': return array('テナントとして入居','自社物件','オーナーとして所有','分からない');
    }
    return array();
}

/* =========================================================================
 * 2. 有効化: リード保存テーブル作成
 * ======================================================================= */
register_activation_hook(__FILE__, 'eaf_activate');
function eaf_activate() {
    global $wpdb;
    $table = $wpdb->prefix . 'eamber_form_leads';
    $charset = $wpdb->get_charset_collate();
    // dbDeltaは「1カラム1行」でないと既存テーブルへのカラム追加を取りこぼす
    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        name VARCHAR(100) NULL,
        kana VARCHAR(100) NULL,
        tel VARCHAR(50) NULL,
        email VARCHAR(191) NULL,
        contact_time VARCHAR(50) NULL,
        ptype VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        details LONGTEXT NULL,
        building VARCHAR(50) NULL,
        ownership VARCHAR(50) NULL,
        detail VARCHAR(2000) NULL,
        timing VARCHAR(50) NULL,
        marketing_opt_in TINYINT(1) DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    eaf_ensure_columns();
    eaf_ensure_form_page();
}

/** 保存先カラムの一覧（スキーマから自動生成）: col => 最大長 */
function eaf_lead_columns() {
    $cols = array();
    foreach (array(eaf_customer_fields(), eaf_situation_fields()) as $flds) {
        foreach ($flds as $fd) {
            if (!empty($fd['col'])) $cols[$fd['col']] = isset($fd['len']) ? (int)$fd['len'] : 191;
        }
    }
    return $cols;
}

/* dbDeltaの取りこぼし対策: 不足カラムを明示的にALTERで追加（確実）。
   ここを怠ると insert がそのキーで丸ごと失敗し、リードが1件も溜まらない事故になる。 */
function eaf_ensure_columns() {
    global $wpdb;
    $t = $wpdb->prefix . 'eamber_form_leads';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) return;
    $cols = $wpdb->get_col("SHOW COLUMNS FROM `$t`", 0);
    if (!is_array($cols)) return;
    $need = array(
        'address' => 'VARCHAR(255) NULL',
        'details' => 'LONGTEXT NULL',
        'ptype'   => 'VARCHAR(20) NULL',
    );
    foreach (eaf_lead_columns() as $c => $len) {
        $need[$c] = 'VARCHAR(' . $len . ') NULL';
    }
    foreach ($need as $c => $def) {
        if (!in_array($c, $cols, true)) {
            $wpdb->query("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        }
    }
}

/**
 * お問い合わせページ（ティザーの遷移先）を用意する。
 *
 * ・スラッグ form の固定ページを「下書き」で作る。いきなり公開すると、
 *   運営者情報も入れないうちにページが世に出てしまうため。
 * ・すでに同じスラッグのページがあれば、それをお問い合わせページとして使う（勝手に増やさない）。
 * ・★一度作ったら二度と自動作成しない。利用者が意図的に消したのに、
 *   更新のたびに復活すると迷惑なため。
 */
function eaf_ensure_form_page() {
    if (!function_exists('wp_insert_post')) return;

    // すでに設定済みで、そのページが生きているなら何もしない
    $id = (int) eaf_opt('form_page_id', 0);
    if ($id > 0 && get_post_status($id) !== false) return;

    if (get_option('eaf_page_created')) return;   // 作成済み（消された場合も再作成しない）

    $o = get_option(EAF_OPT, array());
    if (!is_array($o)) $o = array();

    // 同じスラッグのページが既にあるならそれを採用
    $existing = get_page_by_path('form', OBJECT, 'page');
    if ($existing) {
        $o['form_page_id'] = (int) $existing->ID;
        update_option(EAF_OPT, $o);
        update_option('eaf_page_created', '1');
        return;
    }

    $new_id = wp_insert_post(array(
        'post_title'   => 'お問い合わせ',
        'post_name'    => 'form',
        'post_status'  => 'draft',
        'post_type'    => 'page',
        // Gutenbergのショートコードブロックで入れる（クラシックの塊にしない）
        'post_content' => "<!-- wp:shortcode -->\n[eamber_form]\n<!-- /wp:shortcode -->",
    ));
    update_option('eaf_page_created', '1');
    if ($new_id && !is_wp_error($new_id)) {
        $o['form_page_id'] = (int) $new_id;
        update_option(EAF_OPT, $o);
        update_option('eaf_page_notice', '1');   // 管理画面で1回だけ知らせる
    }
}

/** お問い合わせページのURL（未設定なら空） */
function eaf_form_url() {
    $id = (int) eaf_opt('form_page_id', 0);
    if ($id <= 0 || get_post_status($id) === false) return '';
    $url = get_permalink($id);
    return $url ? $url : '';
}

/**
 * 入力項目の既定値（必須／任意／非表示）の版。
 *
 * ★設定を一度でも保存すると、全項目のモードがDBに書き込まれる。
 *   保存値は既定より優先されるので、これが無いと
 *   「コードの既定を変えたのに、保存済みの環境では何も変わらない」状態になる。
 *   ここを上げた更新では、保存済みのモードを一度だけ捨てて既定に戻す。
 */
define('EAF_FIELD_DEFAULTS_VER', '2');   // 2 = 既定を「必須項目だけ表示」に整理

function eaf_maybe_reset_field_modes() {
    if (get_option('eaf_field_defaults_ver') === EAF_FIELD_DEFAULTS_VER) return;
    $o = get_option(EAF_OPT, array());
    if (is_array($o)) {
        foreach (array_keys($o) as $k) {
            if (strpos($k, 'mode_') === 0) unset($o[$k]);
        }
        update_option(EAF_OPT, $o);
    }
    update_option('eaf_field_defaults_ver', EAF_FIELD_DEFAULTS_VER);
}

/* 自動更新でバージョンが上がったらテーブル定義を追従（新カラム追加等） */
add_action('plugins_loaded', 'eaf_maybe_upgrade');
function eaf_maybe_upgrade() {
    eaf_maybe_reset_field_modes();
    if (get_option('eaf_db_ver') !== EAF_VER) {
        eaf_activate();
        update_option('eaf_db_ver', EAF_VER);
    }
}

/* =========================================================================
 * 3. 設定
 * ======================================================================= */
function eaf_opt($key, $default = '') {
    $o = get_option(EAF_OPT, array());
    return isset($o[$key]) && $o[$key] !== '' ? $o[$key] : $default;
}

/** チェックボックス型の設定（空文字＝OFFを正しく区別する。eaf_opt では OFF にできない） */
function eaf_flag($key, $default = false) {
    $o = get_option(EAF_OPT, array());
    if (!is_array($o) || !array_key_exists($key, $o)) return $default;
    return $o[$key] === '1';
}

/** 項目のモード: 'req'（必須） / 'opt'（任意） / 'off'（非表示） */
function eaf_mode($group, $key, $def = 'opt') {
    $o = get_option(EAF_OPT, array());
    $k = 'mode_' . $group . '_' . $key;
    if (!is_array($o) || !array_key_exists($k, $o)) return $def;
    return in_array($o[$k], array('req', 'opt', 'off'), true) ? $o[$k] : $def;
}

/** そのグループの表示対象だけを返す（非表示を除外） */
function eaf_visible_fields($group, $flds, $req_only = false) {
    $out = array();
    foreach ($flds as $fd) {
        $m = eaf_mode($group, $fd['key'], $fd['def']);
        if ($m === 'off') continue;
        if ($req_only && $m !== 'req') continue;
        $fd['mode'] = $m;
        $out[] = $fd;
    }
    return $out;
}

/* お問い合わせページを自動作成したことを1回だけ知らせる（下書きなので公開操作が要る） */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (!get_option('eaf_page_notice')) return;
    $id = (int) eaf_opt('form_page_id', 0);
    if ($id <= 0) { delete_option('eaf_page_notice'); return; }
    delete_option('eaf_page_notice');
    echo '<div class="notice notice-info is-dismissible"><p><strong>【電気工事反響フォーム】お問い合わせページを下書きで作成しました。</strong><br>'
       . '「お問い合わせ」（スラッグ <code>form</code>）という固定ページに <code>[eamber_form]</code> を入れてあります。'
       . '内容を確認して公開してください。<br>'
       . '<a class="button button-primary" href="' . esc_url(get_edit_post_link($id)) . '">ページを編集する</a> '
       . '<a class="button" href="' . esc_url(admin_url('admin.php?page=eamber-form')) . '">設定を開く</a></p></div>';
});

/**
 * サイトが https でなければ強く警告する。
 * このフォームはお住まいの市町村・お名前・電話番号を送信する。http のままだと
 * その内容が暗号化されずに流れ、公衆Wi-Fi等では第三者に読み取られる。
 * ※管理画面だけ https という構成もあるので、判定は home_url()（お客様が見る側）で行う。
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (eaf_site_is_https()) return;
    echo '<div class="notice notice-error"><p><strong>【電気工事反響フォーム】このサイトは https ではありません。</strong><br>'
       . 'お客様が入力した<strong>お名前・電話番号・お住まいの市町村が、暗号化されずに送信されます</strong>。'
       . '公衆Wi-Fiなどでは第三者に読み取られます。'
       . 'サーバーでSSL証明書を有効にし、「設定 → 一般」のサイトアドレスを <code>https://</code> に変更してください。</p></div>';
});

function eaf_site_is_https() {
    return strpos(strtolower((string) home_url()), 'https://') === 0;
}

/* 公開前チェック。お客様に見える信頼性の材料が抜けたまま公開されるのを防ぐ */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $miss = array();
    if (eaf_opt('operator_contact', '') === '') $miss[] = '電話番号';
    if (eaf_opt('privacy_url', '') === '')      $miss[] = 'プライバシーポリシーURL';
    if (!$miss) return;
    echo '<div class="notice notice-warning"><p><strong>【電気工事反響フォーム】公開前に未設定の項目があります：'
       . esc_html(implode(' / ', $miss)) . '</strong><br>'
       . '電話番号はフォームの冒頭と受付完了メールに表示されます（急ぎのお客様は電話が最短のため）。'
       . '<a href="' . esc_url(admin_url('admin.php?page=eamber-form')) . '">設定画面</a>から設定できます。</p></div>';
});

add_action('admin_menu', function () {
    add_menu_page('電気工事反響フォーム', '電気工事反響フォーム', 'manage_options', 'eamber-form', 'eaf_settings_page', 'dashicons-lightbulb', 58);
    add_submenu_page('eamber-form', '設定', '設定', 'manage_options', 'eamber-form', 'eaf_settings_page');
    add_submenu_page('eamber-form', '反響一覧', '反響一覧', 'manage_options', 'eamber-form-leads', 'eaf_leads_page');
});

add_action('admin_init', function () {
    register_setting('eaf_group', EAF_OPT, 'eaf_sanitize_options');
});

/* 設定画面で「メディアから選ぶ」を使えるようにする（画像選択ダイアログ） */
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'eamber-form') !== false) wp_enqueue_media();
});

function eaf_sanitize_options($in) {
    if (!is_array($in)) $in = array();
    $out = array(
        'site_name'        => sanitize_text_field($in['site_name'] ?? '株式会社e.Amber'),
        'operator_contact' => sanitize_text_field($in['operator_contact'] ?? ''),
        'operator_address' => sanitize_text_field($in['operator_address'] ?? ''),
        'operator_email'   => sanitize_email($in['operator_email'] ?? ''),
        'from_email'       => sanitize_email($in['from_email'] ?? ''),
        'notify_email'     => sanitize_email($in['notify_email'] ?? ''),
        'privacy_url'      => esc_url_raw($in['privacy_url'] ?? ''),
        'terms_url'        => esc_url_raw($in['terms_url'] ?? ''),
        // チェックボックス（未送信＝OFF。'' ではなく明示的に '0' を入れて区別する）
        'notify_on'        => !empty($in['notify_on'])      ? '1' : '0',
        // フォーム上に問い合わせ先（電話番号）を出すか。既定はOFF
        'show_marketing'   => !empty($in['show_marketing']) ? '1' : '0',
        'show_note'        => !empty($in['show_note'])      ? '1' : '0',
        'step_form'        => !empty($in['step_form'])      ? '1' : '0',
        // スパム対策
        'spam_block_link'  => !empty($in['spam_block_link'])  ? '1' : '0',
        'spam_require_ja'  => !empty($in['spam_require_ja'])  ? '1' : '0',
        'spam_words'       => sanitize_textarea_field($in['spam_words'] ?? ''),
        'logo_url'         => esc_url_raw($in['logo_url'] ?? ''),
        // ティザーの見出しまわり（空欄なら表示しない）
        'teaser_badge'     => sanitize_text_field($in['teaser_badge'] ?? ''),
        'teaser_tags'      => sanitize_text_field($in['teaser_tags'] ?? ''),
        'form_page_id'    => (int) ($in['form_page_id'] ?? 0),
        // 自動返信メール
        'mail_subject'     => sanitize_text_field($in['mail_subject'] ?? ''),
        'mail_body'        => sanitize_textarea_field($in['mail_body'] ?? ''),
        // 見出し・ボタン
        'lead_text'        => sanitize_textarea_field($in['lead_text'] ?? ''),
        // 装飾（色）
        'color_brand'      => sanitize_hex_color($in['color_brand'] ?? '')    ?: '#1f6feb',
        // 空欄ならブランドカラーを使う（ボタンだけ目立つ色にしたい場合に指定）
        'color_btn_bg'     => sanitize_hex_color($in['color_btn_bg'] ?? '')   ?: '',
        'color_btn_text'   => sanitize_hex_color($in['color_btn_text'] ?? '') ?: '#ffffff',
        'color_title'      => sanitize_hex_color($in['color_title'] ?? '')    ?: '#1f6feb',
        'color_badge'      => sanitize_hex_color($in['color_badge'] ?? '')    ?: '#ff5a36',
    );
    // 各項目のモード（必須／任意／非表示）。スキーマを回して必ず明示値を保存する
    foreach (eaf_all_groups() as $g => $flds) {
        foreach ($flds as $fd) {
            $k = 'mode_' . $g . '_' . $fd['key'];
            $v = isset($in[$k]) ? $in[$k] : $fd['def'];
            $out[$k] = in_array($v, array('req', 'opt', 'off'), true) ? $v : $fd['def'];
        }
    }
    return $out;
}

/* =========================================================================
 * 4. 工事内容の対応表
 * ======================================================================= */
$GLOBALS['EAF_PTYPE_LABEL'] = array(
    'aircon'   => 'エアコン（取り付け・修理）',
    'breaker'  => 'ブレーカーが落ちる・漏電・分電盤',
    'intercom' => 'インターホン',
    'outlet'   => 'コンセント・スイッチ',
    'light'    => '照明・LED化',
    'fan'      => '換気扇',
    'business' => '店舗・事務所・工場の設備',
    'other'    => 'その他・分からない',
);

/* タイル選択用の短い表記（タイルに長い正式名を入れると2行に折れて選びにくいため） */
$GLOBALS['EAF_PTYPE_SHORT'] = array(
    'aircon'   => 'エアコン',
    'breaker'  => 'ブレーカー・漏電',
    'intercom' => 'インターホン',
    'outlet'   => 'コンセント・スイッチ',
    'light'    => '照明・LED',
    'fan'      => '換気扇',
    'business' => '店舗・事務所・工場',
    'other'    => 'その他・分からない',
);

/* =========================================================================
 * 5. 入力値の正規化・検証
 * ======================================================================= */

/** 全角数字・全角ハイフンを半角へ。type="number" だと全角入力が空になって送信できないため、
 *  数値項目は type="text" + inputmode で受け、ここで直す。 */
function eaf_to_hankaku($s) {
    $s = (string)$s;
    if ($s === '') return $s;
    if (function_exists('mb_convert_kana')) $s = mb_convert_kana($s, 'a', 'UTF-8');
    return strtr($s, array(
        '０'=>'0','１'=>'1','２'=>'2','３'=>'3','４'=>'4','５'=>'5','６'=>'6','７'=>'7','８'=>'8','９'=>'9',
        'ー'=>'-','－'=>'-','−'=>'-','．'=>'.','　'=>' ',
    ));
}

/** 数値項目の妥当性。空文字は「未入力」として呼び出し側で判定する */
function eaf_num_error($fd, $val) {
    $label = $fd['label'];
    if ($val === '') return '';
    if (!is_numeric($val)) return '「' . $label . '」は数字でご入力ください。';
    if ((float)$val < 0)   return '「' . $label . '」は0以上でご入力ください。';
    if (in_array($fd['key'], array('br_age', 'ic_year'), true)) {
        $y = (int)$val; $now = (int)date('Y');
        if ($y < 1900 || $y > $now + 1) return '「' . $label . '」は西暦（例：2005）でご入力ください。';
    }
    return '';
}

/** 電話番号の形式。数字9〜11桁（ハイフン・括弧・空白は許容）＋国際表記の先頭+も許容 */
function eaf_tel_valid($tel) {
    $d = preg_replace('/[^0-9]/', '', eaf_to_hankaku($tel));
    return ($d !== null && strlen($d) >= 9 && strlen($d) <= 11);
}

/**
 * 文字数でDBカラム長に収める（超過するとinsertが失敗してリードが消える）。
 * ★mbstring が無いサーバーで substr を使うと、UTF-8の文字の途中で切れて壊れた
 *   バイト列になり、DBが受け付けず「保存したはずのお申し込みが消える」事故になる。
 *   さらに VARCHAR(n) の n は文字数なので、バイト数で切ると日本語は1/3しか入らない。
 *   よって mbstring が無い場合は preg の /u で文字単位に分解して切る。
 */
function eaf_trim_len($s, $max) {
    $s = (string)$s;
    if ($s === '') return $s;
    if (function_exists('mb_substr')) return mb_substr($s, 0, $max);
    if (preg_match_all('/./us', $s, $m) && isset($m[0])) {
        return implode('', array_slice($m[0], 0, $max));
    }
    return substr($s, 0, $max);
}

/* #rrggbb → "r,g,b"（ブランド色を rgba() で薄く使うため） */
function eaf_hex_to_rgb($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '31,111,235';
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}

/* =========================================================================
 * 6. 濫用対策
 *    公開フォームは誰でも任意アドレス宛にメールを送れる＝爆撃の踏み台にされ、
 *    送信ドメインのレピュテーションが死ぬ。
 *    ※ nonce は未ログインだと全訪問者で同一値・最大24時間有効のためボット対策にならない。
 * ======================================================================= */

/** 送信元IP。CDN配下で全員が同一IP扱いになるのを避けるため標準ヘッダを優先する。
 *  偽装可能だが、本命の防御はメールアドレス単位の制限（爆撃したい宛先は固定のため）。 */
function eaf_client_ip() {
    foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR') as $h) {
        if (!empty($_SERVER[$h])) {
            $parts = explode(',', $_SERVER[$h]);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function eaf_rate_ok($bucket, $id, $limit, $window) {
    if ($id === '') return true;
    $k = 'eaf_rl_' . $bucket . '_' . md5(strtolower($id));
    $n = (int) get_transient($k);
    if ($n >= $limit) return false;
    set_transient($k, $n + 1, $window);
    return true;
}

function eaf_rl_limits() {
    return array(
        'ip_max'       => (int) apply_filters('eaf_rl_ip_max', 5),                 // 同一IP: 1時間に5件
        'ip_window'    => (int) apply_filters('eaf_rl_ip_window', HOUR_IN_SECONDS),
        'email_max'    => (int) apply_filters('eaf_rl_email_max', 3),              // 同一メール: 24時間に3件
        'email_window' => (int) apply_filters('eaf_rl_email_window', DAY_IN_SECONDS),
    );
}

/** ハニーポットと経過時間でボットを弾く。JSでfetch送信するため eaf_elapsed は必ず入る。 */
function eaf_bot_errors() {
    if (!empty($_POST['eaf_website'])) return array('送信を受け付けられませんでした。');
    $elapsed = isset($_POST['eaf_elapsed']) ? intval($_POST['eaf_elapsed']) : 0;
    if ($elapsed < 3000) return array('入力が早すぎます。もう一度お試しください。');
    return array();
}

/**
 * スパム判定。日本語の問い合わせフォームには出てこない特徴を見る。
 *
 * ★引っかかった理由は返さない。どの条件で弾かれたかをボットに学習させないため、
 *   ハニーポットと同じ「送信を受け付けられませんでした。」だけを返す。
 * ★お客様を取りこぼす損失の方が大きいので、誤判定の起きにくいものだけを既定で有効にする。
 */
function eaf_spam_hit($values) {
    $block_link = eaf_flag('spam_block_link', true);
    $words = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) eaf_opt('spam_words', ''))), 'strlen'));

    foreach ($values as $v) {
        $v = (string) $v;
        if (trim($v) === '') continue;

        // 1) リンクの埋め込み。工事の問い合わせでURLを書く理由がない
        if ($block_link && preg_match('#https?://|www\.[a-z0-9-]+\.|\[url|\[link|</?a\s#iu', $v)) return true;

        // 2) 日本の電気工事の問い合わせには出てこない文字種（キリル・アラビア・タイ）
        if (preg_match('/[\x{0400}-\x{04FF}\x{0600}-\x{06FF}\x{0E00}-\x{0E7F}]/u', $v)) return true;

        // 3) 管理画面で登録したNGワード
        foreach ($words as $w) {
            $hit = function_exists('mb_stripos') ? mb_stripos($v, $w) : stripos($v, $w);
            if ($hit !== false) return true;
        }
    }
    return false;
}

/**
 * お名前に日本語が1文字も含まれないか。
 * 海外のボットは名前をローマ字で入れることが多いので効き目は大きいが、
 * ローマ字で書く方や外国籍の方まで弾いてしまうため、既定はオフ。
 */
function eaf_name_not_japanese($name) {
    if (!eaf_flag('spam_require_ja', false)) return false;
    $name = preg_replace('/\s+/u', '', (string) $name);
    if ($name === '') return false;
    return !preg_match('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}\x{3005}-\x{3007}\x{FF66}-\x{FF9F}]/u', $name);
}

/**
 * CSVインジェクション対策。= + - @ 等で始まるセルは Excel が数式として実行してしまうため、
 * 先頭に ' を付けて無害な文字列にする（お客様の自由入力がそのままCSVに入るため必須）。
 * 数値（-5 等）はそのまま通す。
 */
function eaf_csv_safe($s) {
    $s = (string)$s;
    if ($s === '' || is_numeric($s)) return $s;
    return (strpos("=+-@\t\r", $s[0]) !== false) ? "'" . $s : $s;
}

/* =========================================================================
 * 7. メール
 * ======================================================================= */

/* 受付完了メールの初期本文（お客様へ・差し込みタグ付き） */
function eaf_default_mail_body() {
    return "【{site_name}】お問い合わせを受け付けました\n\n"
        . "{customer_name}様\n\n"
        . "この度はお問い合わせいただきありがとうございます。\n"
        . "以下の内容で受け付けました。\n\n"
        . "{customer_details}\n\n"
        . "{property_details}\n\n"
        . "担当者が内容を確認のうえ、ご入力いただいたご連絡先へご連絡いたします。\n"
        . "いましばらくお待ちください。";
}

/**
 * メールの末尾に付ける会社の連絡先（署名）。
 *
 * ★ここは問い合わせが済んだ後なので、断り書きではなく連絡先を出す。
 *   フォーム上で電話番号を伏せる設定にしていても、このメールには必ず載せる
 *   （受け付けた後にお客様が連絡できなくなるのを防ぐため）。
 */
function eaf_mail_footer() {
    /* 運営＝施工が同じ会社（自社サイトのフォーム）なので、社名はサイト名に一本化している */
    $name  = eaf_opt('site_name', '株式会社e.Amber');
    $addr  = eaf_opt('operator_address', '');
    $tel   = eaf_opt('operator_contact', '');
    $mail  = eaf_opt('operator_email', '');

    $line = "───────────────────────────────\n";
    $out  = $line . "本件に関するお問い合わせは下記までお願いいたします。\n\n";
    if ($name !== '') $out .= $name . "\n";
    if ($addr !== '') $out .= "所在地 : " . $addr . "\n";
    if ($tel  !== '') $out .= "電話   : " . $tel . "\n";
    if ($mail !== '') $out .= "メール : " . $mail . "\n";
    return $out . rtrim($line);
}

/**
 * 連絡先は必ず付ける。本文テンプレートは管理画面で自由に書き換えられるため、
 * テンプレートの中に置くと編集した瞬間に消えてしまう。よってテンプレートの外で連結する。
 */
function eaf_with_footer($body) {
    // mbstring が無いサーバーでも動くよう strpos を使う（UTF-8同士の検索は strpos で正しく判定できる）
    // 利用者が本文に自分で連絡先を書いている場合は二重にしない
    if (strpos($body, '本件に関するお問い合わせは下記まで') === false) {
        $body .= "\n\n" . eaf_mail_footer();
    }
    return $body . "\n";
}

function eaf_mail_body($ctx) {
    $tmpl = eaf_opt('mail_body', '');
    if (trim($tmpl) === '') $tmpl = eaf_default_mail_body();

    $repl = array(
        '{site_name}'         => eaf_opt('site_name', '株式会社e.Amber'),
        '{customer_name}'     => isset($ctx['name']) ? $ctx['name'] : '',
        '{customer_details}'  => isset($ctx['customer_details']) ? $ctx['customer_details'] : '',
        '{property_details}'  => isset($ctx['property_details']) ? $ctx['property_details'] : '',
        '{ptype}'             => isset($ctx['ptype_label']) ? $ctx['ptype_label'] : '',
        '{address}'           => isset($ctx['address']) ? $ctx['address'] : '',
        '{city}'              => isset($ctx['city']) ? $ctx['city'] : '',
        '{email}'             => isset($ctx['email']) ? $ctx['email'] : '',
        '{tel}'               => isset($ctx['tel']) ? $ctx['tel'] : '',
        '{operator_name}'     => eaf_opt('site_name', ''),
        '{operator_contact}'  => eaf_opt('operator_contact', ''),
    );
    // 未設定の項目で「お問い合わせ: 」「 様」のようにラベルだけが残らないよう、その行ごと落とす
    // ★会社の連絡先はメール末尾（eaf_mail_footer）に必ず入る。本文側にも署名を書くと
    //   会社名と電話が2回出るため、署名タグを含む行はここで落とす。
    //   以前の初期文面には署名2行が入っており、それを保存済みの環境が多いので、
    //   「空のときだけ消す」ではなく常に消す。
    $tmpl = preg_replace('/^.*\{operator_contact\}.*\R?/m', '', $tmpl);
    $tmpl = preg_replace('/^.*\{operator_name\}.*\R?/m', '', $tmpl);
    if (trim($repl['{customer_name}'])    === '') $tmpl = preg_replace('/^\h*\{customer_name\}\h*様\h*\R?/m', '', $tmpl);
    $body = strtr($tmpl, $repl);
    // 行ごと削除した箇所に空行が二重に残るため、3行以上の連続改行は2行に畳む
    $body = preg_replace("/(\R){3,}/", "\n\n", $body);
    return eaf_with_footer(rtrim($body));
}

/* 件名テンプレ */
function eaf_mail_subject() {
    $s = eaf_opt('mail_subject', '');
    if (trim($s) === '') $s = '【{site_name}】お問い合わせを受け付けました';
    return strtr($s, array('{site_name}' => eaf_opt('site_name', '株式会社e.Amber')));
}

/**
 * 管理者通知メールの本文（担当者へ）。
 * ★営業連絡の可否（オプトイン有無）を必ず明記する。
 *   担当者が特定電子メール法に違反する営業メールを送ってしまう事故を防ぐため。
 */
function eaf_admin_notify_body($ctx) {
    $b  = "お問い合わせが届きました。\n\n";
    $b .= "───── お客様情報 ─────\n";
    $b .= "■ メール : " . (!empty($ctx['email']) ? $ctx['email'] : '（未入力・お電話で折り返してください）') . "\n";
    if (!empty($ctx['customer_details'])) $b .= $ctx['customer_details'] . "\n";
    $b .= "\n───── 工事内容・ご状況 ─────\n";
    $b .= (isset($ctx['property_details']) ? $ctx['property_details'] : '') . "\n";
    $b .= "\n───── 営業連絡について ─────\n";
    $b .= !empty($ctx['marketing'])
        ? "○ 営業案内メールの受け取りに同意いただいています。\n"
        : "× 営業案内メールの受け取りには同意されていません。\n  今回のお申し込みへのご対応以外の営業メールは送らないでください（特定電子メール法）。\n";
    $b .= "\n管理画面「電気工事反響フォーム → 反響一覧」からも確認できます。";
    return $b;
}

/**
 * 「更新を確認」ボタン。
 * WordPressの自動チェックは最大12時間おきで、押さないと新版に気づけない。
 * こちらのキャッシュとWP側の更新情報を捨てて、その場で確認し直す。
 */
add_action('admin_post_eaf_check_update', 'eaf_check_update');
function eaf_check_update() {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('eaf_check_update');
    delete_transient('eaf_updater_' . md5(plugin_basename(__FILE__)));
    delete_site_transient('update_plugins');
    if (function_exists('wp_update_plugins')) wp_update_plugins();
    wp_safe_redirect(admin_url('admin.php?page=eamber-form&checked=1'));
    exit;
}

/** 配信されている最新バージョン（分からなければ空） */
function eaf_latest_version() {
    $t = get_site_transient('update_plugins');
    $me = plugin_basename(__FILE__);
    if (is_object($t)) {
        if (!empty($t->response[$me]->new_version))  return $t->response[$me]->new_version;
        if (!empty($t->no_update[$me]->new_version)) return $t->no_update[$me]->new_version;
    }
    return '';
}

/* テストメール送信（迷惑メール判定・文面の確認用） */
add_action('admin_post_eaf_test_mail', 'eaf_test_mail');
function eaf_test_mail() {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('eaf_test_mail');
    $to = wp_get_current_user()->user_email;
    $ctx = array(
        'name' => '山田 太郎', 'email' => $to, 'tel' => '090-1234-5678',
        'ptype_label' => 'エアコン（取り付け・修理）', 'address' => '甲府市',
        'city' => '甲府市',
        'customer_details' => "■ お名前 : 山田 太郎\n■ 電話番号 : 090-1234-5678\n■ ご連絡しやすい時間帯 : 午後（12〜17時）",
        'property_details' => "■ 工事内容 : エアコン（取り付け・修理）\n■ 現場の市町村 : 甲府市\n■ 建物の種類 : 戸建て\n■ ご希望の作業 : 入れ替えたい\n■ 設置する階 : 2階\n■ 専用コンセントの有無 : 分からない",
    );
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    $from = eaf_opt('from_email'); $site = eaf_opt('site_name', '株式会社e.Amber');
    if ($from) $headers[] = 'From: ' . $site . ' <' . $from . '>';
    $ok = wp_mail($to, '[テスト] ' . eaf_mail_subject(), eaf_mail_body($ctx), $headers);
    wp_safe_redirect(admin_url('admin.php?page=eamber-form&testmail=' . ($ok ? '1' : '0') . '&to=' . rawurlencode($to)));
    exit;
}

/* =========================================================================
 * 8. 管理画面：設定
 * ======================================================================= */
/**
 * 色の入力欄。カラーピッカーだけだと「いま何番の色なのか」が分からず、
 * ブランドカラーの指定（#1f6feb など）を貼り付けることもできないため、
 * ★HEXのテキスト入力を主にして、ピッカーは横に並べる。両者は双方向に同期する。
 */
function eaf_color_field($key, $default) {
    $v = eaf_opt($key, $default);
    ob_start(); ?>
    <span class="fhs-colorfield">
      <input type="color" class="fhs-color-pick" value="<?php echo esc_attr($v); ?>" aria-label="カラーピッカーで選ぶ">
      <input type="text" class="fhs-color-hex code" name="<?php echo EAF_OPT; ?>[<?php echo esc_attr($key); ?>]"
             value="<?php echo esc_attr($v); ?>" maxlength="7" size="9" spellcheck="false" autocomplete="off"
             placeholder="<?php echo esc_attr($default); ?>">
      <button type="button" class="button button-small fhs-color-reset" data-default="<?php echo esc_attr($default); ?>">初期値</button>
    </span>
<?php return ob_get_clean();
}

/**
 * 画像を選ぶ欄（プレビュー＋メディアライブラリ＋消す）。
 * 複数置けるようにIDは使わずクラスで組む。
 * $round を true にすると、プレビューを丸で表示する（実際の表示に合わせるため）。
 */
function eaf_image_field($key, $round = false) {
    $url = eaf_opt($key);
    ob_start(); ?>
    <div class="fhs-logofield">
      <span class="fhs-logo-preview<?php echo $url ? '' : ' is-empty'; ?><?php echo $round ? ' is-round' : ''; ?>">
        <?php if ($url): ?><img src="<?php echo esc_url($url); ?>" alt=""><?php else: ?>未設定<?php endif; ?>
      </span>
      <input type="url" class="fhs-logo-url" name="<?php echo EAF_OPT; ?>[<?php echo esc_attr($key); ?>]"
             value="<?php echo esc_attr($url); ?>" size="52" placeholder="https://example.com/logo.png">
      <button type="button" class="button fhs-logo-pick">メディアから選ぶ</button>
      <button type="button" class="button fhs-logo-clear">消す</button>
    </div>
<?php return ob_get_clean();
}

function eaf_settings_page() {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    ?>
    <style>
      .fhs-colorfield{display:inline-flex;align-items:center;gap:8px}
      .fhs-colorfield input[type=color]{width:46px;height:34px;padding:2px;border:1px solid #8c8f94;border-radius:4px;background:#fff;cursor:pointer;flex:0 0 auto}
      .fhs-colorfield input[type=text]{width:104px;font-family:monospace;text-transform:lowercase}
      .fhs-colorfield input[type=text].fhs-bad{border-color:#d63638;box-shadow:0 0 0 1px #d63638}
      .fhs-logofield{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
      .fhs-logo-preview{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border:1px solid #dcdcde;border-radius:6px;background:#fff;overflow:hidden;flex:0 0 auto}
      .fhs-logo-preview img{max-width:100%;max-height:100%;width:auto;height:auto;display:block}
      .fhs-logo-preview.is-empty{color:#8c8f94;font-size:11px;background:#f6f7f7}
      .fhs-logo-preview.is-round{border-radius:50%}
      .fhs-logo-preview.is-round img{width:100%;height:100%;object-fit:cover}
      .fhs-recipes td{vertical-align:middle}
      .fhs-recipes .fhs-copy-src{display:block;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:8px 10px;font-size:12.5px;line-height:1.6;word-break:break-all;user-select:all}
      .fhs-recipes .fhs-copy{white-space:nowrap}
    </style>
    <div class="wrap">
        <h1>電気工事反響フォーム 設定</h1>
        <?php if (isset($_GET['testmail'])) {
            $tm_ok = ($_GET['testmail'] === '1');
            $tm_to = isset($_GET['to']) ? sanitize_email(wp_unslash($_GET['to'])) : '';
            echo '<div class="notice notice-' . ($tm_ok ? 'success' : 'error') . '"><p>' .
                ($tm_ok
                    ? 'テストメールを <strong>' . esc_html($tm_to) . '</strong> に送信しました。届かない場合は<strong>迷惑メールフォルダ</strong>も確認してください（届かない＝SPF/DKIM未設定の可能性大）。'
                    : 'テストメールの送信に失敗しました。WP Mail SMTP などの送信設定を確認してください。') .
                '</p></div>';
        } ?>
        <?php
        $eaf_latest = eaf_latest_version();
        $eaf_has_new = ($eaf_latest !== '' && version_compare(EAF_VER, $eaf_latest, '<'));
        if (isset($_GET['checked'])) {
            echo '<div class="notice notice-' . ($eaf_has_new ? 'warning' : 'success') . ' is-dismissible"><p>'
               . ($eaf_has_new
                   ? '新しいバージョン <strong>' . esc_html($eaf_latest) . '</strong> があります。'
                     . '<a href="' . esc_url(admin_url('plugins.php')) . '">プラグイン画面</a>から更新してください。'
                   : 'このプラグインは最新です。')
               . '</p></div>';
        }
        ?>
        <p style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span>ページに <code>[eamber_form]</code> を貼ると申込フォームが表示されます。詳しい書き方は「<strong>使い方</strong>」タブへ。</span>
        </p>
        <p style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:-6px 0 14px">
            <span class="description">バージョン <strong><?php echo esc_html(EAF_VER); ?></strong><?php
                if ($eaf_has_new) echo '　<span style="color:#b32d2e">最新は ' . esc_html($eaf_latest) . ' です</span>';
                elseif ($eaf_latest !== '') echo '　（最新です）';
            ?></span>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=eaf_check_update'), 'eaf_check_update')); ?>">更新を確認</a>
            <?php if ($eaf_has_new): ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('plugins.php')); ?>">プラグイン画面で更新する</a>
            <?php endif; ?>
        </p>
        <h2 class="nav-tab-wrapper" id="fhs-tabs">
            <a href="#" class="nav-tab nav-tab-active" data-tab="basic">基本設定</a>
            <a href="#" class="nav-tab" data-tab="fields">入力項目</a>
            <a href="#" class="nav-tab" data-tab="mail">自動返信メール</a>
            <a href="#" class="nav-tab" data-tab="style">デザイン</a>
            <a href="#" class="nav-tab" data-tab="usage">使い方</a>
        </h2>
        <form method="post" action="options.php">
            <?php settings_fields('eaf_group'); ?>

            <div class="fhs-tabpanel" data-tab="basic">
            <table class="form-table">
                <tr><th>会社名（サイト名）</th><td><input type="text" name="<?php echo EAF_OPT; ?>[site_name]" value="<?php echo esc_attr(eaf_opt('site_name', '株式会社e.Amber')); ?>" size="40">
                    <p class="description">メールの件名・本文の署名、フォームの利用目的の主語に使われます。運営と施工が同じ会社なので、会社名はこの1箇所だけです。</p></td></tr>
                <tr><th>電話番号（お客様向け）</th><td>
                    <input type="text" name="<?php echo EAF_OPT; ?>[operator_contact]" value="<?php echo esc_attr(eaf_opt('operator_contact')); ?>" size="40" placeholder="例：055-000-0000">
                    <p class="description"><strong>フォームの冒頭</strong>（お急ぎの方はお電話ください）と<strong>受付完了メールの末尾</strong>に表示されます。工事の問い合わせは急ぎが多く、電話が最短の導線です。</p></td></tr>
                <tr><th>問い合わせメール</th><td>
                    <input type="email" name="<?php echo EAF_OPT; ?>[operator_email]" value="<?php echo esc_attr(eaf_opt('operator_email')); ?>" size="40" placeholder="info@example.com">
                    <p class="description">受付完了メールの末尾に載る、お客様からの問い合わせ先です。通知を受け取る「通知先メール（担当者）」とは別に指定できます。</p></td></tr>
                <tr><th>所在地</th><td><input type="text" name="<?php echo EAF_OPT; ?>[operator_address]" value="<?php echo esc_attr(eaf_opt('operator_address')); ?>" size="50" placeholder="例：山梨県甲府市○○1-2-3">
                    <p class="description">受付完了メールの末尾の署名に載ります（任意）。</p></td></tr>
                <tr><th>送信元メール</th><td><input type="email" name="<?php echo EAF_OPT; ?>[from_email]" value="<?php echo esc_attr(eaf_opt('from_email')); ?>" size="40" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <p class="description">お客様への受付完了メールの差出人。空欄ならWordPressの既定の差出人になります。到達率のため WP Mail SMTP 等で SPF/DKIM を設定してください。</p></td></tr>
                <tr><th>通知先メール（担当者）</th><td>
                    <input type="email" name="<?php echo EAF_OPT; ?>[notify_email]" value="<?php echo esc_attr(eaf_opt('notify_email')); ?>" size="40"><br>
                    <label style="display:inline-block;margin-top:8px"><input type="checkbox" name="<?php echo EAF_OPT; ?>[notify_on]" value="1" <?php checked(eaf_flag('notify_on', true)); ?>> 申し込みが届いたら通知する</label>
                    <p class="description">空欄なら送信元メール（無ければ管理者アドレス）に通知します。<strong>工事の問い合わせは急ぎのお客様が多いので、通知は必ずONを推奨します。</strong></p></td></tr>
                <tr><th>フォーム冒頭の案内文</th><td>
                    <textarea name="<?php echo EAF_OPT; ?>[lead_text]" rows="3" style="width:100%;max-width:760px"><?php echo esc_textarea(eaf_opt('lead_text')); ?></textarea>
                    <p class="description">フォームの一番上に表示される案内文（任意）。例：「症状とお住まいの市町村が分かれば話が始められます。まずはお気軽にお問い合わせください。」</p></td></tr>
                <tr><th>お問い合わせページ</th><td>
                    <?php
                    $sid = (int) eaf_opt('form_page_id', 0);
                    if (function_exists('wp_dropdown_pages')) {
                        wp_dropdown_pages(array(
                            'name'              => EAF_OPT . '[form_page_id]',
                            'selected'          => $sid,
                            'show_option_none'  => '― 選択してください ―',
                            'option_none_value' => 0,
                        ));
                    }
                    $surl = eaf_form_url();
                    if ($sid > 0 && $surl) {
                        $st = get_post_status($sid);
                        echo ' <a class="button" href="' . esc_url(get_edit_post_link($sid)) . '">編集</a> ';
                        echo '<a class="button" href="' . esc_url($surl) . '" target="_blank" rel="noopener">表示</a>';
                        if ($st !== 'publish') {
                            echo '<p class="description" style="color:#b32d2e"><strong>このページはまだ下書きです。</strong>内容を確認して公開してください（公開するまでお客様には表示されません）。</p>';
                        }
                    }
                    ?>
                    <p class="description">
                        <strong><code>[eamber_form]</code> を貼ったお問い合わせページ</strong>を選びます。ティザーの「送信」で移動する先です。<br>
                        ここを設定しておけば、ティザーのショートコードに <code>url="…"</code> を書かなくて済みます。<br>
                        <span class="description">※ プラグインを有効化したとき、スラッグ <code>form</code> の固定ページを<strong>下書きで自動作成</strong>しています（同じスラッグのページが既にある場合はそれを使います）。</span>
                    </p>
                </td></tr>
                <tr><th>プライバシーポリシーURL</th><td><input type="url" name="<?php echo EAF_OPT; ?>[privacy_url]" value="<?php echo esc_attr(eaf_opt('privacy_url')); ?>" size="50"></td></tr>
                <tr><th>利用規約・免責URL</th><td><input type="url" name="<?php echo EAF_OPT; ?>[terms_url]" value="<?php echo esc_attr(eaf_opt('terms_url')); ?>" size="50"></td></tr>
            </table>
            </div>
            <div class="fhs-tabpanel" data-tab="fields" style="display:none">
            <h3>入力項目の設定</h3>
            <p class="description" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px 12px;max-width:860px">
                項目ごとに <strong>必須／任意／非表示</strong> を選べます。<br>
                <strong>項目を増やすほど申込数は減り、リードの質は上がります。</strong>まずは必須を絞り、
                足りない情報は担当者がお電話で聞く運用をおすすめします。<br>
                ※ <strong>工事内容・現場の市町村・同意チェック</strong>は常に必須です（連絡と見積りに不可欠なため、切り替えできません）。<br>
                ※ <strong>メールアドレスは常に任意</strong>です。ご入力があったお客様にだけ受付完了メールを送ります（電話が主戦場のため、必須にして離脱させません）。
            </p>
            <?php
            $groups = array(
                'customer'  => array('お客様のご連絡先', 'お名前・電話番号は、折り返しのご連絡に必要な中心項目です。'),
                'situation' => array('ご状況', '担当者が優先順位を付け、初回の連絡で的確に話すための情報です。'),
            );
            foreach (eaf_property_fields() as $pt => $flds) {
                $groups['prop_' . $pt] = array('工事内容別：' . $GLOBALS['EAF_PTYPE_LABEL'][$pt], '「' . $GLOBALS['EAF_PTYPE_LABEL'][$pt] . '」が選ばれたときだけ表示される項目です。');
            }
            $all = eaf_all_groups();
            foreach ($groups as $g => $meta):
                $flds = $all[$g];
            ?>
            <h4 style="margin:26px 0 6px"><?php echo esc_html($meta[0]); ?></h4>
            <p class="description" style="margin:0 0 8px"><?php echo esc_html($meta[1]); ?></p>
            <table class="widefat striped" style="max-width:640px">
                <thead><tr><th style="width:46%">項目</th><th style="width:18%">必須</th><th style="width:18%">任意</th><th style="width:18%">非表示</th></tr></thead>
                <tbody>
                <?php foreach ($flds as $fd):
                    $k = 'mode_' . $g . '_' . $fd['key'];
                    $cur = eaf_mode($g, $fd['key'], $fd['def']);
                ?>
                    <tr>
                        <td><?php echo esc_html($fd['label']); ?></td>
                        <?php foreach (array('req','opt','off') as $m): ?>
                        <td><label style="display:block"><input type="radio" name="<?php echo EAF_OPT . '[' . $k . ']'; ?>" value="<?php echo $m; ?>" <?php checked($cur, $m); ?>></label></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>

            <h4 style="margin:26px 0 6px">スパム対策</h4>
            <p class="description" style="max-width:860px">
                すでに<strong>ハニーポット・送信までの経過時間・同一IP/同一メールの回数制限</strong>が常時働いています。
                ここでは、それを抜けてきた場合の追加の網を設定します。<br>
                <strong>引っかかった送信には理由を伝えません</strong>（どこで弾かれたかをボットに学習させないため）。
            </p>
            <table class="form-table">
                <tr><th>リンクを含む申し込み</th><td>
                    <label><input type="checkbox" name="<?php echo EAF_OPT; ?>[spam_block_link]" value="1" <?php checked(eaf_flag('spam_block_link', true)); ?>> URL（http://… など）が入力されていたら受け付けない</label>
                    <p class="description">
                        工事の問い合わせでURLを書く理由はまずないので、<strong>オンのままを推奨します。</strong>
                        宣伝リンクを貼る典型的なスパムを止められます。<br>
                        ※あわせて、キリル文字・アラビア文字・タイ文字が含まれる申し込みは常に受け付けません（設定不要）。
                    </p>
                </td></tr>
                <tr><th>お名前の日本語チェック</th><td>
                    <label><input type="checkbox" name="<?php echo EAF_OPT; ?>[spam_require_ja]" value="1" <?php checked(eaf_flag('spam_require_ja', false)); ?>> お名前に日本語が1文字も含まれない場合は受け付けない</label>
                    <p class="description">
                        海外からのスパムによく効きますが、<strong>お名前をローマ字で書く方や外国籍の方も弾いてしまいます。</strong><br>
                        <strong>既定はオフ</strong>です。実際にスパムが増えてきたらオンにしてください。
                    </p>
                </td></tr>
                <tr><th>NGワード</th><td>
                    <textarea name="<?php echo EAF_OPT; ?>[spam_words]" rows="4" style="width:100%;max-width:520px" placeholder="1行に1語ずつ&#10;例：SEO対策&#10;例：ビットコイン"><?php echo esc_textarea(eaf_opt('spam_words')); ?></textarea>
                    <p class="description">
                        1行に1語。入力欄のどこかにこの語が含まれていたら受け付けません（大文字小文字は区別しません）。<br>
                        実際に届いたスパムの特徴的な単語を足していく使い方を想定しています。<strong>短すぎる語は誤爆します</strong>のでご注意ください。
                    </p>
                </td></tr>
            </table>

            <h4 style="margin:26px 0 6px">見せ方</h4>
            <table class="form-table"><tr><th>ステップ表示</th><td>
                <label><input type="checkbox" name="<?php echo EAF_OPT; ?>[step_form]" value="1" <?php checked(eaf_flag('step_form', true)); ?>> お問い合わせページのフォームを「お困りの内容 → ご連絡先」の2ステップに分けて表示する</label>
                <p class="description">
                    一画面に全部並べるより<strong>途中離脱が減ります</strong>（大手の問い合わせフォームはほぼこの形です）。<br>
                    進み具合のバーが出て、「次へ進む」を押すたびにその画面の必須項目だけを確認します。
                    <strong>お名前・電話番号は必ず最後のステップ</strong>で聞きます。<br>
                    ティザーで入力済みのステップは自動で飛ばします。オフにすると従来どおり1画面に全項目を表示します。
                </p>
            </td></tr></table>

            <h4 style="margin:26px 0 6px">そのほかの欄</h4>
            <table class="form-table"><tr><th>表示する項目</th><td>
                <label><input type="checkbox" name="<?php echo EAF_OPT; ?>[show_note]" value="1" <?php checked(eaf_flag('show_note', false)); ?>> 「備考・ご要望」の自由入力欄（★「症状・ご希望」と重複するため既定はオフ）</label><br>
                <label><input type="checkbox" name="<?php echo EAF_OPT; ?>[show_marketing]" value="1" <?php checked(eaf_flag('show_marketing', true)); ?>> 「営業案内メールを希望」チェック欄</label>
                <p class="description">営業案内メールのチェックは<strong>同意の証拠</strong>になります（特定電子メール法）。オフにすると、今回の申し込み以外の営業メールは送れません。</p>
            </td></tr></table>
            </div>

            <div class="fhs-tabpanel" data-tab="mail" style="display:none">
            <table class="form-table">
                <tr><th>件名</th><td>
                    <input type="text" name="<?php echo EAF_OPT; ?>[mail_subject]" value="<?php echo esc_attr(eaf_opt('mail_subject')); ?>" size="60" placeholder="【{site_name}】お問い合わせを受け付けました">
                    <p class="description">空欄で初期件名。</p>
                </td></tr>
                <tr><th>本文</th><td>
                    <textarea name="<?php echo EAF_OPT; ?>[mail_body]" rows="20" style="width:100%;max-width:760px;font-family:monospace;font-size:13px"><?php echo esc_textarea(eaf_opt('mail_body') ?: eaf_default_mail_body()); ?></textarea>
                    <p class="description">
                        空欄にして保存すると初期文面に戻ります。使える差し込みタグ：<br>
                        <code>{site_name}</code> <code>{customer_name}</code> <code>{customer_details}</code>（お客様情報のまとまり） <code>{property_details}</code>（工事内容のまとまり） <code>{ptype}</code>（工事内容） <code>{address}</code>（市町村） <code>{email}</code> <code>{tel}</code><br>
                        <span class="description">※会社名・電話などの署名は<strong>本文に書く必要はありません</strong>。「基本設定」タブの会社名・電話番号・問い合わせメール・所在地がメール末尾に自動で入ります（本文に書くと二重になるため、書かれていても取り除きます）。</span>
                    </p>
                    <p class="description" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px 12px;margin-top:10px">
                        <strong>メールの末尾には会社の連絡先（署名）が自動で付きます。</strong><br>
                        内容は<a href="#" class="fhs-gotab" data-tab="basic">基本設定</a>タブのものです。
                        本文には<strong>ご案内したい内容だけ</strong>をお書きください。
                    </p>
                </td></tr>
                <tr><th>到達確認</th><td>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=eaf_test_mail'), 'eaf_test_mail')); ?>" class="button">テストメールを自分宛に送信</a>
                    <p class="description">
                        現在の件名・本文テンプレートでサンプルを送ります（保存してから押してください）。<br>
                        <strong>迷惑メールに入る場合</strong>は「WP Mail SMTP」等でSMTP送信にし、送信ドメインの <code>SPF</code> / <code>DKIM</code> / <code>DMARC</code> を設定してください。
                    </p>
                </td></tr>
            </table>
            </div>

            <div class="fhs-tabpanel" data-tab="style" style="display:none">
            <h3>ティザーの見出し</h3>
            <p class="description">記事に置く入口フォーム（ティザー）の見出しまわりの設定です。<strong>空欄にした項目は表示されません。</strong></p>
            <table class="form-table">
                <tr><th>バッジ</th><td>
                    <input type="text" name="<?php echo EAF_OPT; ?>[teaser_badge]" value="<?php echo esc_attr(eaf_opt('teaser_badge')); ?>" size="30" placeholder="例：無料・秘密厳守">
                    <p class="description">見出しの左（縦のときは上）に出る小さなバッジ。空欄なら表示しません。</p>
                </td></tr>
                <tr><th>メリットのタグ</th><td>
                    <input type="text" name="<?php echo EAF_OPT; ?>[teaser_tags]" value="<?php echo esc_attr(eaf_opt('teaser_tags')); ?>" size="60" placeholder="例：見積り無料, 県内同一料金, 相談だけOK">
                    <p class="description">
                        見出しの右（縦のときは下）に並ぶタグ。<strong>カンマ（,）で区切ります</strong>（、や | でも構いません）。<br>
                        <strong>3つくらいまでが読みやすい</strong>です。空欄なら表示しません。<br>
                        例：<code>見積り無料, 県内同一料金, 相談だけOK</code> ／ <code>しつこい営業なし, その日に折り返し</code>
                    </p>
                </td></tr>
                <tr><th>アイコン画像</th><td>
                    <?php echo eaf_image_field('logo_url'); ?>
                    <p class="description">
                        会社ロゴやファビコンなど。<strong>見出しの左に小さく表示されます</strong>（高さは見出しに合わせて自動調整）。<br>
                        正方形に近い画像がきれいに収まります。空欄なら表示しません。
                    </p>
                </td></tr>
            </table>
            <p class="description">いずれも、ショートコードで <code>badge="…"</code> <code>tags="…"</code> <code>logo="…"</code> を指定すると、そのフォームだけ差し替えられます。</p>

            <h3>フォームの色</h3>
            <p class="description">カラーコード（<code>#1f6feb</code> のような6桁）を直接入力できます。左の四角を押すとカラーピッカーからも選べます。</p>
            <table class="form-table">
                <tr><th>ブランドカラー</th><td>
                    <?php echo eaf_color_field('color_brand', '#1f6feb'); ?>
                    <p class="description">入力済みチェック（✓）、次の入力欄のハイライト、工事内容で選んだタイル、メリットのタグに使われます。</p>
                </td></tr>
                <tr><th>ボタンの背景色</th><td>
                    <?php echo eaf_color_field('color_btn_bg', '#e65100'); ?>
                    <p class="description">
                        送信ボタン・「次へ進む」ボタンの背景色。<strong>空欄なら <code>#e65100</code>（オレンジ）</strong>です。<br>
                        問い合わせ系サイトのボタンは<strong>暖色（オレンジ〜赤）</strong>が定番です。
                        色そのものより「<strong>まわりから浮いているか</strong>」が効くので、紺や白が基調のページでは暖色が有利です。<br>
                        ブランドカラーに合わせたい場合は、上のブランドカラーと同じ値を入れてください。
                    </p>
                </td></tr>
                <tr><th>ボタンの文字色</th><td><?php echo eaf_color_field('color_btn_text', '#ffffff'); ?></td></tr>
                <tr><th>見出しの色</th><td>
                    <?php echo eaf_color_field('color_title', '#1f6feb'); ?>
                    <p class="description">ティザーの見出し（例：60秒でかんたん入力）の文字色。</p>
                </td></tr>
                <tr><th>「必須」バッジの色</th><td>
                    <?php echo eaf_color_field('color_badge', '#ff5a36'); ?>
                    <p class="description">未入力の項目に付くバッジと、ティザーの「無料・秘密厳守」バッジ。入力すると「ブランドカラーの ✓」に変わります。</p>
                </td></tr>
            </table>
            <p class="description">初期値：ブランド <code>#1f6feb</code> ／ ボタン文字 <code>#ffffff</code> ／ 見出し <code>#1f6feb</code> ／ バッジ <code>#ff5a36</code><br>
                空欄のまま保存すると初期値に戻ります。</p>
            </div>

            <div class="fhs-tabpanel" data-tab="usage" style="display:none">
            <h3>ショートコードの貼り方</h3>
            <table class="widefat striped" style="max-width:900px">
                <thead><tr><th style="width:170px">用途</th><th>ショートコード</th></tr></thead>
                <tbody>
                <tr><td><strong>標準</strong></td>
                    <td><code>[eamber_form]</code><br><span class="description">全項目・幅100%・枠なし。</span></td></tr>
                <tr><td><strong>コンパクト</strong><br><span class="description">サイドバー等</span></td>
                    <td><code>[eamber_form design="compact"]</code><br><span class="description">必須項目のみ・幅440pxのカード。</span></td></tr>
                <tr><td><strong>カード</strong></td>
                    <td><code>[eamber_form design="card"]</code><br><span class="description">全項目を枠＋影のカードで表示。</span></td></tr>
                <tr style="background:#fffbe6"><td><strong>ティザー（横長）</strong><br><span class="description">記事の途中・記事末</span></td>
                    <td><code>[eamber_form design="teaser"]</code><br>
                        <span class="description"><strong>入力欄が横一列に並ぶ</strong>横長タイプ。2〜3項目だけ入力してもらい、ボタンで<strong>お問い合わせページ</strong>へ。入力値は自動で引き継がれます。工事内容は<strong>タイルを1タップ</strong>で選べます。<br>
                        幅は既定で本文いっぱい。<strong>狭い場所やスマホでは自動的に縦積みに切り替わります。</strong></span></td></tr>
                <tr style="background:#fffbe6"><td><strong>ティザー（縦）</strong><br><span class="description">サイドバー・記事末</span></td>
                    <td><code>[eamber_form design="teaser-v"]</code><br>
                        <span class="description">同じ内容を<strong>常に縦積み</strong>・幅440pxのカードで。サイドバーなど幅の狭い場所向け。</span></td></tr>
                </tbody>
            </table>

            <h3>ティザー（入口フォーム）の使い方</h3>
            <p class="description" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px 12px;max-width:900px">
                いきなり全項目のフォームを見せると身構えられてしまいます。<strong>記事の中には「2〜3項目だけのティザー」を置き、
                続きはお問い合わせページで書いてもらう</strong>のが定石です。<br>
                ティザーで入力した内容はお問い合わせページに<strong>自動で引き継がれ</strong>、「↓ 続きはこちらから」と案内して
                <strong>次に書く欄まで自動でスクロール</strong>します。あと何項目で終わるかも表示されるので、離脱が減ります。
            </p>
            <table class="widefat striped" style="max-width:900px">
                <thead><tr><th style="width:110px">属性</th><th>意味</th></tr></thead>
                <tbody>
                <tr><td><code>url</code></td><td>ボタンの遷移先。<strong>ふだんは書かなくて構いません</strong>（設定→基本設定→「お問い合わせページ」で選んだページへ送ります）。<br>
                    そのフォームだけ別のページへ送りたいときに指定します。<br>
                    <strong>例：</strong><code>url="https://kai-denkou.com/contact/"</code><br>
                    <span class="description">同じサイト内なら <code>url="/○○○/form/"</code> のように <code>/</code> で始まる書き方でも構いません。</span></td></tr>
                <tr><td><code>fields</code></td><td>聞く項目と順番。<code>ptype</code>（工事内容）/ <code>address</code>（市町村）/ <code>timing</code>（希望時期）から選ぶ。省略時は <code>ptype,address</code><br>
                    <span class="description">※ <strong>お名前・電話番号・メールはティザーには置けません。</strong>個人情報は、利用目的の明示と同意チェックがあるお問い合わせページで受け取る決まりにしているためです。</span></td></tr>
                <tr><td><code>width</code></td><td>横幅。<strong>省略時は横長＝本文の幅いっぱい、縦＝440px</strong>（どちらも中央寄せ）。<code>width="820"</code> のように数字だけ書けばpx、<code>width="100%"</code> も指定できます。<br>
                    <span class="description">狭くすると入力欄は自動的に縦積みへ切り替わります（おおむね560px以下から）。</span></td></tr>
                <tr><td><code>title</code></td><td>見出し（省略時：60秒でかんたん入力）</td></tr>
                <tr><td><code>subtitle</code></td><td>小見出し（省略時は表示なし）</td></tr>
                <tr><td><code>badge</code></td><td>見出しの左（縦のときは上）のバッジ。<strong>ふだんは「デザイン」タブで設定</strong>し、ここではそのフォームだけ変えたいときに使います。<code>badge=""</code> でそのフォームだけ非表示</td></tr>
                <tr><td><code>tags</code></td><td>見出しの右（縦のときは下）に並ぶメリットのタグ。カンマ区切り。例：<code>tags="見積り無料,県内同一料金,相談だけOK"</code><br>
                    <span class="description">こちらも「デザイン」タブで設定すれば全ティザーに反映されます。</span></td></tr>
                <tr><td><code>steps</code></td><td><code>steps="0"</code> で「STEP 1」「STEP 2」の表記を消す</td></tr>
                <tr><td><code>logo</code></td><td>見出しの左に出すアイコン画像のURL。<strong>ふだんは「デザイン」タブで設定すれば全部のティザーに出ます</strong>ので、ここで指定するのは<strong>そのフォームだけ画像を変えたいとき</strong>です</td></tr>
                <tr><td><code>note</code></td><td>フォーム下の小さな注記。<strong><code>|</code>（縦棒）で改行</strong>できます<br>
                    <span class="description">例：<code>note="しつこい営業はいたしません|見積りは無料です"</code>　省略時は「入力内容は次のページに引き継がれます。／この時点ではまだ送信されません。」の2行</span></td></tr>
                <tr><td><code>button</code></td><td>ボタンの文言（省略時：無料で相談する）</td></tr>
                </tbody>
            </table>
            <h3>そのままコピーして使えます</h3>
            <p class="description">
                <strong>そのまま貼るだけで使えます。</strong>遷移先は
                <a href="<?php echo esc_url(admin_url('admin.php?page=eamber-form')); ?>">設定 → 基本設定 → お問い合わせページ</a>
                で選んだページになるので、ショートコードにURLを書く必要はありません。<br>
                <span class="description">別のページへ送りたいときだけ <code>url="…"</code> を足してください（下の属性表を参照）。</span>
            </p>
            <table class="widefat striped fhs-recipes" style="max-width:980px">
                <thead><tr><th style="width:190px">やりたいこと</th><th>ショートコード</th><th style="width:90px"></th></tr></thead>
                <tbody>
                <tr>
                    <td><strong>記事の途中に置く</strong><br><span class="description">いちばん基本。入力欄が横一列</span></td>
                    <td><code class="fhs-copy-src">[eamber_form design="teaser"]</code></td>
                    <td><button type="button" class="button fhs-copy">コピー</button></td>
                </tr>
                <tr>
                    <td><strong>幅を抑える</strong><br><span class="description">本文が広いときに</span></td>
                    <td><code class="fhs-copy-src">[eamber_form design="teaser" width="820"]</code></td>
                    <td><button type="button" class="button fhs-copy">コピー</button></td>
                </tr>
                <tr>
                    <td><strong>サイドバーに置く</strong><br><span class="description">縦積み・幅440px</span></td>
                    <td><code class="fhs-copy-src">[eamber_form design="teaser-v"]</code></td>
                    <td><button type="button" class="button fhs-copy">コピー</button></td>
                </tr>
                <tr>
                    <td><strong>見出しを地域に合わせる</strong><br><span class="description">エリア記事向け</span></td>
                    <td><code class="fhs-copy-src">[eamber_form design="teaser" title="甲府市の電気工事を相談する" subtitle="ご入力は60秒。しつこい営業はいたしません"]</code></td>
                    <td><button type="button" class="button fhs-copy">コピー</button></td>
                </tr>
                <tr>
                    <td><strong>希望時期も聞く</strong><br><span class="description">横一列に3項目</span></td>
                    <td><code class="fhs-copy-src">[eamber_form design="teaser" fields="ptype,address,timing"]</code></td>
                    <td><button type="button" class="button fhs-copy">コピー</button></td>
                </tr>
                <tr>
                    <td><strong>お問い合わせページ本体</strong><br><span class="description">遷移先のページに貼る</span></td>
                    <td><code class="fhs-copy-src">[eamber_form]</code></td>
                    <td><button type="button" class="button fhs-copy">コピー</button></td>
                </tr>
                </tbody>
            </table>
            <p class="description" style="background:#fff8e6;border-left:4px solid #dba617;padding:10px 12px;max-width:980px;margin-top:12px">
                <strong>書き方の注意：属性と属性の間には必ず半角スペースを入れてください。</strong><br>
                × <code>url="/○○○/form/"width="640"</code>　→　○ <code>url="/○○○/form/" width="640"</code><br>
                <span class="description">※ スペースが抜けていても動くようにしてありますが、その場合はフォームの上に
                （管理者にだけ見える）お知らせが出ます。</span>
            </p>
            <p class="description">
                <strong>横長と縦の違い：</strong>横長（<code>teaser</code>）は<strong>入力欄が横一列に並びます</strong>。縦（<code>teaser-v</code>）は常に縦積みです。<br>
                横長は幅が足りなくなると自動で縦積みに切り替わるので、スマホでもそのまま使えます。
            </p>
            <p class="description">属性 <code>button</code> はどのデザインでも使えます。例：<code>[eamber_form button="無料で見積りを相談する"]</code></p>

            <h3>問い合わせ後の動き</h3>
            <ol>
                <li>メール入力があったお客様に<strong>受付完了メール</strong>を自動返信（内容は「自動返信メール」タブで編集可）</li>
                <li><strong>通知先メール（担当者）</strong>に問い合わせ内容を通知（営業連絡の可否つき）</li>
                <li>担当者がお客様へ連絡し、日程・見積りをご案内</li>
            </ol>

            <h3 style="color:#b32d2e">法的な注意</h3>
            <p class="description" style="max-width:900px">
                このフォームは<strong>自社対応の前提</strong>で作られており、同意文にも「第三者に提供することはありません」と明記されます。
                集めたお名前・電話番号を<strong>同意なく他社に渡すのは違法です</strong>（個情法27条）。
                公開前に弁護士等の確認を推奨します。
            </p>
            </div>

            <div id="fhs-save"><?php submit_button(); ?></div>
        </form>
    </div>
    <script>
    (function(){
        /* 色欄：HEXテキストとカラーピッカーを双方向に同期する */
        function expand(v){   // #abc → #aabbcc（input[type=color] は6桁しか受け付けない）
            return v.length === 4 ? '#' + v[1]+v[1] + v[2]+v[2] + v[3]+v[3] : v;
        }
        function norm(v){
            v = String(v == null ? '' : v).trim().toLowerCase();
            if (v !== '' && v.charAt(0) !== '#') v = '#' + v;
            return /^#([0-9a-f]{3}|[0-9a-f]{6})$/.test(v) ? v : null;
        }
        document.querySelectorAll('.fhs-colorfield').forEach(function(f){
            var pick = f.querySelector('.fhs-color-pick'),
                hex = f.querySelector('.fhs-color-hex'),
                reset = f.querySelector('.fhs-color-reset');
            pick.addEventListener('input', function(){ hex.value = pick.value; hex.classList.remove('fhs-bad'); });
            hex.addEventListener('input', function(){
                var v = norm(hex.value);
                if (v) { pick.value = expand(v); hex.classList.remove('fhs-bad'); }
                else hex.classList.toggle('fhs-bad', hex.value.trim() !== '');   // 空欄は初期値に戻る指定として許す
            });
            hex.addEventListener('blur', function(){
                var v = norm(hex.value);
                if (v) hex.value = v;                       // #ABC → #abc に整える
                else if (hex.value.trim() !== '') { hex.value = pick.value; hex.classList.remove('fhs-bad'); }
            });
            reset.addEventListener('click', function(){
                hex.value = reset.getAttribute('data-default');
                pick.value = reset.getAttribute('data-default');
                hex.classList.remove('fhs-bad');
            });
        });

        /* 画像を選ぶ欄。ページ内にいくつあっても動くようクラスで走査する。
           wp.media が使えない環境でも、URLを直接貼れば動く。 */
        document.querySelectorAll('.fhs-logofield').forEach(function(field){
            var input = field.querySelector('.fhs-logo-url');
            var preview = field.querySelector('.fhs-logo-preview');
            var pick = field.querySelector('.fhs-logo-pick');
            var clear = field.querySelector('.fhs-logo-clear');
            if (!input) return;
            var frame = null;
            function render(){
                var url = input.value.trim();
                if (url) { preview.innerHTML = '<img alt="">'; preview.querySelector('img').src = url; preview.classList.remove('is-empty'); }
                else { preview.textContent = '未設定'; preview.classList.add('is-empty'); }
            }
            input.addEventListener('input', render);
            if (clear) clear.addEventListener('click', function(){ input.value = ''; render(); });
            if (pick) pick.addEventListener('click', function(){
                if (!window.wp || !window.wp.media) { input.focus(); return; }   // 使えなければ手入力に任せる
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: '画像を選ぶ', button: { text: 'この画像を使う' },
                                   library: { type: 'image' }, multiple: false });
                frame.on('select', function(){
                    input.value = frame.state().get('selection').first().toJSON().url;
                    render();
                });
                frame.open();
            });
        });

        /* ショートコードのコピーボタン。
           管理画面が https でないと navigator.clipboard が使えないため、
           その場合は選択＋execCommand に落とす（社内の http 環境でも動くように）。 */
        document.querySelectorAll('.fhs-copy').forEach(function(btn){
            btn.addEventListener('click', function(){
                var src = btn.closest('tr').querySelector('.fhs-copy-src');
                var text = src.textContent.trim();
                function done(ok){
                    var label = btn.textContent;
                    btn.textContent = ok ? 'コピーしました' : '選択しました';
                    setTimeout(function(){ btn.textContent = label; }, 1600);
                }
                function fallback(){
                    var r = document.createRange();
                    r.selectNodeContents(src);
                    var sel = window.getSelection();
                    sel.removeAllRanges(); sel.addRange(r);
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) {}
                    done(ok);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function(){ done(true); }, fallback);
                } else fallback();
            });
        });

        var tabs = document.querySelectorAll('#fhs-tabs .nav-tab');
        var panels = document.querySelectorAll('.fhs-tabpanel');
        var save = document.getElementById('fhs-save');
        function showTab(name){
            tabs.forEach(function(x){ x.classList.toggle('nav-tab-active', x.getAttribute('data-tab') === name); });
            panels.forEach(function(p){ p.style.display = (p.getAttribute('data-tab') === name) ? '' : 'none'; });
            if (save) save.style.display = (name === 'usage') ? 'none' : ''; // 使い方タブでは保存ボタンを隠す
        }
        tabs.forEach(function(t){
            t.addEventListener('click', function(e){
                e.preventDefault();
                showTab(t.getAttribute('data-tab'));
            });
        });
        // 説明文の中から別タブへ飛ぶリンク
        document.querySelectorAll('.fhs-gotab').forEach(function(a){
            a.addEventListener('click', function(e){
                e.preventDefault();
                showTab(a.getAttribute('data-tab'));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    })();
    </script>
<?php }

/**
 * 直近の保存エラーを控える。
 * MySQLのエラー文には値が混ざることがある（例: Duplicate entry '…' for key）ため、
 * 全ページ読み込みで展開される autoload には載せず、長さも切り詰める。
 */
function eaf_record_db_error($msg) {
    $msg = eaf_trim_len((string) $msg, 300) . ' @ ' . current_time('mysql');
    if (get_option('eaf_last_db_error') === false) {
        add_option('eaf_last_db_error', $msg, '', 'no');
    } else {
        update_option('eaf_last_db_error', $msg, 'no');
    }
}

/* =========================================================================
 * 9. 管理画面：申込一覧
 * ======================================================================= */
function eaf_leads_page() {
    // ★メニュー経由でなくても個人情報を出さない。add_submenu_page の権限指定だけに頼らない
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    global $wpdb;
    $table = $wpdb->prefix . 'eamber_form_leads';
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 200");
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
    $export = wp_nonce_url(admin_url('admin-post.php?action=eaf_export_leads'), 'eaf_export_leads');
    echo '<div class="wrap"><h1>反響一覧</h1>';
    if (isset($_GET['deleted'])) echo '<div class="notice notice-success is-dismissible"><p>削除しました。</p></div>';
    $dberr = get_option('eaf_last_db_error');
    if ($dberr) echo '<div class="notice notice-error"><p><strong>直近に保存エラーが発生しました：</strong> ' . esc_html($dberr) . '<br>最新版に更新すると自動修復を試みます。解消されない場合は、この赤いメッセージの文面を共有してください。</p></div>';
    echo '<p>反響件数：' . $total . ' 件（表示は最新200件）　<a class="button button-primary" href="' . esc_url($export) . '">CSVエクスポート（Excel）</a></p>';
    echo '<p class="description">個人情報を含みます。CSVの取り扱いにご注意ください。「営業可」が空欄のお客様には、今回の申し込みへの対応以外の営業メールを送らないでください。</p>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>受付日時</th><th>お名前</th><th>電話</th><th>メール</th><th>工事内容</th><th>市町村</th><th>建物</th><th>時期</th><th>詳細</th><th>営業可</th><th>操作</th></tr></thead><tbody>';
    if ($rows) foreach ($rows as $r) {
        $plabel = isset($GLOBALS['EAF_PTYPE_LABEL'][$r->ptype]) ? $GLOBALS['EAF_PTYPE_LABEL'][$r->ptype] : $r->ptype;
        $det = isset($r->details) ? (string)$r->details : '';
        $del = wp_nonce_url(admin_url('admin-post.php?action=eaf_delete_lead&id=' . $r->id), 'eaf_delete_lead_' . $r->id);
        $g = function ($v) { return ($v !== null && $v !== '') ? $v : '-'; };
        printf('<tr><td>%s</td><td><strong>%s</strong></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>'
             . '<td style="white-space:pre-line;font-size:12px;line-height:1.5">%s</td><td>%s</td>'
             . '<td><a href="%s" onclick="return confirm(\'この反響を削除しますか？\')" style="color:#b32d2e">削除</a></td></tr>',
            esc_html($r->created_at),
            esc_html($g(isset($r->name) ? $r->name : '')),
            esc_html($g(isset($r->tel) ? $r->tel : '')),
            esc_html($g($r->email)),
            esc_html($plabel),
            esc_html($g(isset($r->address) ? $r->address : '')),
            esc_html($g(isset($r->building) ? $r->building : '')),
            esc_html($g(isset($r->timing) ? $r->timing : '')),
            esc_html($det !== '' ? $det : '-'),
            $r->marketing_opt_in ? '○' : '', esc_url($del));
    } else echo '<tr><td colspan="11">まだありません</td></tr>';
    echo '</tbody></table></div>';
}

/* CSVエクスポート（Excel向けShift_JIS） */
add_action('admin_post_eaf_export_leads', 'eaf_export_leads');
function eaf_export_leads() {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('eaf_export_leads');
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eamber_form_leads ORDER BY id DESC", ARRAY_A);
    nocache_headers();
    // Excel は Shift_JIS が最も安全。ただし mbstring が無いサーバーでは変換できないため、
    // その場合は UTF-8 + BOM で出す（BOMがないとExcelが文字化けする）
    $can_sjis = function_exists('mb_convert_encoding');
    header('Content-Type: text/csv; charset=' . ($can_sjis ? 'Shift_JIS' : 'UTF-8'));
    header('Content-Disposition: attachment; filename="eamber_oiawase.csv"');
    $out = fopen('php://output', 'w');
    if (!$can_sjis) fwrite($out, "\xEF\xBB\xBF");
    $head = array('ID','受付日時','お名前','フリガナ','電話番号','メール','連絡希望時間帯','工事内容','市町村','建物の種類','持ち家/賃貸','希望時期','症状・ご希望','詳細','営業同意');
    $cols = array('id','created_at','name','kana','tel','email','contact_time','ptype','address','building','ownership','timing','detail','details','marketing_opt_in');
    $sjis = function ($s) use ($can_sjis) {
        return $can_sjis ? mb_convert_encoding((string)$s, 'SJIS-win', 'UTF-8') : (string)$s;
    };
    fputcsv($out, array_map($sjis, $head));
    foreach ($rows as $r) {
        $line = array();
        foreach ($cols as $c) {
            $v = isset($r[$c]) ? $r[$c] : '';
            if ($c === 'ptype' && isset($GLOBALS['EAF_PTYPE_LABEL'][$v])) $v = $GLOBALS['EAF_PTYPE_LABEL'][$v];
            if ($c === 'marketing_opt_in') $v = $v ? '同意あり' : '同意なし';
            $line[] = $sjis(eaf_csv_safe($v));
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/* リード削除（個人情報の削除依頼対応） */
add_action('admin_post_eaf_delete_lead', 'eaf_delete_lead');
function eaf_delete_lead() {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    $id = intval($_GET['id'] ?? 0);
    check_admin_referer('eaf_delete_lead_' . $id);
    if ($id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'eamber_form_leads', array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=eamber-form-leads&deleted=1'));
    exit;
}

/* =========================================================================
 * 10. AJAX（admin-ajax 経由。REST無効化環境でも動く）
 * ======================================================================= */
/**
 * 新しいnonceを配る。
 * ページキャッシュが効いていると、HTMLに焼き込まれたnonceが古いまま配られ続け、
 * 24時間で失効した後は全員の送信が失敗する。JS側がこれを呼んで取り直せるようにする。
 */
add_action('wp_ajax_eamber_form_nonce', 'eaf_ajax_nonce');
add_action('wp_ajax_nopriv_eamber_form_nonce', 'eaf_ajax_nonce');
function eaf_ajax_nonce() {
    if (!headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    wp_send_json(array('nonce' => wp_create_nonce('eamber_form')));
}

add_action('wp_ajax_eamber_form', 'eaf_ajax');
add_action('wp_ajax_nopriv_eamber_form', 'eaf_ajax');
function eaf_ajax() {
    check_ajax_referer('eamber_form', 'nonce');

    // ボット・自動送信を先に弾く（DB・メールに一切触らせない）
    $bot = eaf_bot_errors();
    if ($bot) wp_send_json(array('ok' => false, 'errors' => $bot));

    $lim     = eaf_rl_limits();
    $compact = !empty($_POST['compact']);

    $ptype   = sanitize_text_field($_POST['ptype'] ?? '');
    // 市町村は必須のセレクト。選択肢外の値はフォーム改ざんとみなして弾く
    $address = sanitize_text_field($_POST['address'] ?? '');
    // sanitize_email は不正な形式を空文字にしてしまう。「入力したのに黙って捨てられた」を
    // 防ぐため、生の入力が空かどうかを先に控えておき、形式エラーはエラーとして返す
    $email_raw = trim((string) wp_unslash($_POST['email'] ?? ''));
    $email     = sanitize_email($email_raw);
    $note    = eaf_flag('show_note', false) ? sanitize_textarea_field($_POST['note_text'] ?? '') : '';
    $agree   = !empty($_POST['agree']);
    $mkt     = eaf_flag('show_marketing', true) && !empty($_POST['marketing']);

    $errors = array();
    if (!$agree) $errors[] = '個人情報の取扱いへの同意が必要です。';
    // メールは任意。入力があったときだけ形式を見る（電話が主戦場のため必須にしない）
    if ($email_raw !== '' && !is_email($email)) $errors[] = 'メールアドレスの形式が正しくありません。';
    if ($address === '' || !in_array($address, eaf_opt_list('city'), true)) $errors[] = 'お住まい・現場の市町村を選択してください。';
    if (!isset($GLOBALS['EAF_PTYPE_LABEL'][$ptype])) $errors[] = '工事内容を選択してください。';

    /**
     * スキーマ駆動で1グループぶんの入力を取得・検証する。
     * $prefix … POSTキーの接頭辞（customer_ / situation_ / mansion__ など）
     * 戻り値 … array(値の連想配列, 表示用の行配列)
     */
    $collect = function ($group, $flds, $prefix) use (&$errors, $compact) {
        $vals = array(); $lines = array();
        foreach (eaf_visible_fields($group, $flds, $compact) as $fd) {
            $pk = $prefix . $fd['key'];
            if ($fd['type'] === 'check') {
                $val = !empty($_POST[$pk]) ? 'はい' : '';
            } elseif ($fd['type'] === 'textarea') {
                $val = sanitize_textarea_field($_POST[$pk] ?? '');
            } else {
                $val = sanitize_text_field($_POST[$pk] ?? '');
            }
            // 形式エラーを出した項目は、続けて「入力してください」まで出すと
            // 1つの間違いで2行のエラーが並び、何を直せばいいのか分からなくなる
            $bad = false;
            if ($fd['type'] === 'number') {
                $val = trim(eaf_to_hankaku($val));
                $ne = eaf_num_error($fd, $val);
                if ($ne !== '') { $errors[] = $ne; $val = ''; $bad = true; }
            }
            // 選択肢は選択肢外の値を無視（フォーム改ざん対策）
            if ($fd['type'] === 'select' && $val !== '' && !in_array($val, eaf_opt_list($fd['opts']), true)) $val = '';
            if ($fd['type'] === 'tel' && $val !== '') {
                // 全角で入力されることが多い。担当者がそのまま発信できるよう半角に揃えて保存する
                $val = trim(eaf_to_hankaku($val));
                if (!eaf_tel_valid($val)) {
                    $errors[] = '「' . $fd['label'] . '」の形式が正しくありません（例：090-1234-5678）。';
                    $val = ''; $bad = true;
                }
            }
            if ($fd['mode'] === 'req' && $val === '' && !$bad) {
                $errors[] = '「' . $fd['label'] . '」を入力してください。';
            }
            // 保存先カラムの長さに、この時点で丸めておく。
            // 後段だけで丸めると、メール・完了画面には長いままの値が出て保存値と食い違う。
            if (!empty($fd['col']) && !empty($fd['len'])) $val = eaf_trim_len($val, $fd['len']);
            $vals[$fd['key']] = array('fd' => $fd, 'val' => $val);
            if ($val !== '') $lines[] = '■ ' . $fd['label'] . ' : ' . $val;
        }
        return array($vals, $lines);
    };

    list($cust, $cust_lines) = $collect('customer',  eaf_customer_fields(),  'customer_');
    list($situ, $situ_lines) = $collect('situation', eaf_situation_fields(), 'situation_');

    // 工事内容ごとの項目
    $prop_lines = array(); $prop_vals = array();
    $schema = eaf_property_fields();
    if (isset($schema[$ptype])) {
        list($prop_vals, $prop_lines) = $collect('prop_' . $ptype, $schema[$ptype], $ptype . '__');
    }

    if ($errors) wp_send_json(array('ok' => false, 'errors' => $errors));

    /* スパム判定。自由入力の欄をまとめて見る。
       ★理由は伝えない（どこで弾かれたかをボットに教えないため）。 */
    $free_text = array($address, $note);
    foreach (array($cust, $situ, $prop_vals) as $set) {
        foreach ($set as $item) $free_text[] = $item['val'];
    }
    $cust_name = isset($cust['name']) ? $cust['name']['val'] : '';
    if (eaf_spam_hit($free_text) || eaf_name_not_japanese($cust_name)) {
        wp_send_json(array('ok' => false, 'errors' => array('送信を受け付けられませんでした。')));
    }

    // ★入力内容が正しいときだけ回数を数える。
    //   入力ミスでも数えると、間違えた正規のお客様が先にブロックされてしまう。
    if (!eaf_rate_ok('ip', eaf_client_ip(), $lim['ip_max'], $lim['ip_window'])) {
        wp_send_json(array('ok' => false, 'errors' => array(
            '送信が集中しています。しばらく時間をおいてからお試しください。')));
    }
    // ★本命の防御：同一アドレス宛の連続送信を止める（第三者のアドレスを入れての爆撃対策）
    if (!eaf_rate_ok('email', $email, $lim['email_max'], $lim['email_window'])) {
        wp_send_json(array('ok' => false, 'errors' => array(
            'このメールアドレスでのお申し込みが続いています。24時間ほどおいてからお試しください。')));
    }

    $label = $GLOBALS['EAF_PTYPE_LABEL'][$ptype];

    // メール・画面用のまとまり
    $customer_details = implode("\n", $cust_lines);
    $prop_head = array('■ 工事内容 : ' . $label, '■ 現場の市町村 : ' . $address);
    $property_details = implode("\n", array_merge($prop_head, $situ_lines, $prop_lines));
    if ($note !== '') $property_details .= "\n■ 備考・ご要望 : " . $note;

    // 「詳細」列に入れるもの＝個別カラムを持たない項目 ＋ 工事内容ごとの項目 ＋ 備考
    $detail_lines = array();
    foreach (array($cust, $situ) as $set) {
        foreach ($set as $item) {
            if (empty($item['fd']['col']) && $item['val'] !== '') {
                $detail_lines[] = '■ ' . $item['fd']['label'] . ' : ' . $item['val'];
            }
        }
    }
    $detail_lines = array_merge($detail_lines, $prop_lines);
    if ($note !== '') $detail_lines[] = '■ 備考・ご要望 : ' . $note;

    // 完了画面の「ご入力内容」用。お名前・電話・種別・住所は表で別に出すため、ここには入れない
    $confirm_lines = array_merge($situ_lines, $prop_lines);
    if ($note !== '') $confirm_lines[] = '■ 備考・ご要望 : ' . $note;

    // リード保存
    global $wpdb;
    $row = array(
        'created_at' => current_time('mysql'),
        'email'      => $email,
        'ptype'      => $ptype,
        'address'    => eaf_trim_len($address, 255),
        'details'    => implode("\n", $detail_lines),
        'marketing_opt_in' => $mkt ? 1 : 0,
    );
    // 個別カラムを持つ項目（お名前・電話番号など）を、カラム長に丸めて格納
    $lens = eaf_lead_columns();
    foreach (array($cust, $situ) as $set) {
        foreach ($set as $item) {
            $col = isset($item['fd']['col']) ? $item['fd']['col'] : null;
            if ($col) $row[$col] = eaf_trim_len($item['val'], isset($lens[$col]) ? $lens[$col] : 191);
        }
    }

    $ins = $wpdb->insert($wpdb->prefix . 'eamber_form_leads', $row);
    if ($ins === false) {
        // 1回目失敗：不足カラムを補ってからもう一度だけ試す
        eaf_record_db_error($wpdb->last_error);
        eaf_ensure_columns();
        $ins = $wpdb->insert($wpdb->prefix . 'eamber_form_leads', $row);
    }
    if ($ins === false) {
        // リトライも失敗：受付できていないので「完了」と偽らず、メールも送らずにエラーを返す
        eaf_record_db_error($wpdb->last_error);
        wp_send_json(array('ok' => false, 'errors' => array(
            '申し訳ありません。ただいま受付処理でエラーが発生しました。お手数ですが時間をおいて再度お試しください。',
        )));
    }
    delete_option('eaf_last_db_error');   // 初回・リトライを問わず、保存に成功したらエラー記録を消す

    $ctx = array(
        'name'  => isset($cust['name']) ? $cust['name']['val'] : '',
        'tel'   => isset($cust['tel'])  ? $cust['tel']['val']  : '',
        'email' => $email,
        'ptype_label' => $label,
        'address' => $address,
        'city'    => $address,
        'customer_details' => $customer_details,
        'property_details' => $property_details,
        'marketing' => $mkt,
    );

    // 受付完了メール（お客様へ）。メール未入力なら送らない（折り返しは電話で行う）
    $site = eaf_opt('site_name', '株式会社e.Amber');
    $from = eaf_opt('from_email');
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    if ($from) $headers[] = 'From: ' . $site . ' <' . $from . '>';
    $mail_ok = ($email !== '') ? wp_mail($email, eaf_mail_subject(), eaf_mail_body($ctx), $headers) : false;

    // 担当者通知
    if (eaf_flag('notify_on', true)) {
        $notify = eaf_opt('notify_email', $from ?: get_option('admin_email'));
        if ($notify) {
            $subj = '【お問い合わせ】' . $label . ' / ' . $address . ($ctx['name'] !== '' ? ' / ' . $ctx['name'] . '様' : '');
            wp_mail($notify, $subj, eaf_admin_notify_body($ctx), $headers);
        }
    }

    wp_send_json(array(
        'ok' => true, 'mail_ok' => (bool)$mail_ok,
        'email' => $email, 'name' => $ctx['name'], 'tel' => $ctx['tel'],
        'ptype_label' => $label, 'address' => $address,
        'confirm_text' => implode("\n", $confirm_lines),
    ));
}

/* =========================================================================
 * 11. ショートコード [eamber_form]
 * ======================================================================= */
add_shortcode('eamber_form', 'eaf_shortcode');
/**
 * デザインパターン:
 *   [eamber_form]                  標準（全項目・幅100%・枠なし）
 *   [eamber_form design="compact"] コンパクト（必須のみ・カード・幅440px）
 *   [eamber_form design="card"]    全項目をカード（枠＋影）で表示
 *   [eamber_form design="teaser"]   入口フォーム・横長（記事の途中に置く）
 *   [eamber_form design="teaser-v"] 入口フォーム・縦（サイドバー）
 *   ※遷移先は設定の「お問い合わせページ」。url 属性で個別に上書きもできる
 *
 * ティザーは2〜3項目だけ聞いて url のページへ送る。入力値は sessionStorage で引き継ぐ
 * （URLのクエリには載せない＝入力内容が履歴やリファラに残らないようにするため）。
 */
function eaf_shortcode($atts = array()) {
    $atts  = eaf_unglue_atts($atts);
    $glued = !empty($atts['eaf_glued']);   // 属性の間のスペースが抜けていた（拾って動かしている）
    unset($atts['eaf_glued']);
    $a = shortcode_atts(array(
        'design' => 'default', 'button' => '',
        // ティザー用
        'url' => '', 'title' => '', 'subtitle' => '', 'note' => '', 'fields' => '',
        'logo' => '', 'badge' => '', 'steps' => '1', 'width' => '', 'tags' => '',
    ), $atts, 'eamber_form');
    $design  = in_array($a['design'], array('default', 'compact', 'card', 'teaser', 'teaser-v'), true) ? $a['design'] : 'default';
    $compact = ($design === 'compact');
    $teaser  = ($design === 'teaser' || $design === 'teaser-v');   // 入口フォーム（本フォームへ引き継ぐ）
    $btn     = $a['button'] !== '' ? sanitize_text_field($a['button'])
                                   : ($teaser ? '無料で相談する' : 'この内容で送信する');

    /* ティザーの遷移先。url を書かなければ、設定で指定したお問い合わせページへ送る
       （ショートコードにURLを毎回書かなくて済むように）。 */
    $t_target = '';
    if ($teaser) {
        $t_target = $a['url'] !== '' ? esc_url_raw($a['url']) : eaf_form_url();
    }
    $t_title  = $a['title']    !== '' ? sanitize_text_field($a['title'])    : '60秒でかんたん入力';
    $t_sub    = $a['subtitle'] !== '' ? sanitize_text_field($a['subtitle']) : '';
    $t_note   = $a['note']     !== '' ? sanitize_text_field($a['note'])     : '';
    /* 注記は行ごとに分けて出す（幅によって変な位置で折り返さないように）。
       note属性では「|」で行を区切れる。 */
    $t_note_lines = $t_note !== ''
        ? array_values(array_filter(array_map('trim', explode('|', $t_note)), 'strlen'))
        : array('入力内容は次のページに引き継がれます。', 'この時点ではまだ送信されません。');
    // アイコンは設定のものを使い、ショートコードで指定があればそちらを優先する
    $t_logo   = $a['logo']     !== '' ? esc_url_raw($a['logo'])             : eaf_opt('logo_url', '');
    /* バッジとタグは「設定画面で決めて全ティザーに反映」が基本。
       ショートコードで指定があればそのフォームだけ上書き。
       どちらも空なら表示しない（既定の文言は持たせない）。 */
    $t_badge  = isset($atts['badge']) ? sanitize_text_field($a['badge']) : eaf_opt('teaser_badge', '');
    $t_tags   = eaf_split_tags(isset($atts['tags']) ? $a['tags'] : eaf_opt('teaser_tags', ''));
    $t_steps  = ($a['steps'] !== '0' && $a['steps'] !== '');
    $t_fields = $teaser ? eaf_parse_teaser_fields($a['fields']) : array();

    /* ティザーの横幅。数字だけなら px として扱う（width="500"）。
       width="100%" で本文の幅いっぱいにもできる。既定は横長500px / 縦440px。 */
    $t_width = '';
    if ($teaser) {
        $w = trim((string)$a['width']);
        if ($w !== '' && preg_match('/^\d+$/', $w)) $w .= 'px';
        // 横長は入力欄を横に並べるため、既定は本文の幅いっぱい。縦は440px
        if (!preg_match('/^\d+(px|%|em|rem|vw)$/', $w)) $w = ($design === 'teaser-v') ? '440px' : '100%';
        $t_width = $w;
    }

    $c_brand    = eaf_opt('color_brand', '#1f6feb');
    $c_btn_text = eaf_opt('color_btn_text', '#ffffff');
    /* 送信ボタンの色。未指定なら暖色（オレンジ）。
       問い合わせ系サイトのCTAは暖色（オレンジ〜赤）が定番で、
       紺・白が基調のフォームでは暖色が最も浮く。
       #e65100 は白文字とのコントラストが 3.79:1 あり、大きな太字なら読みやすさの基準を満たす
       （#ff9900 のような明るいオレンジは 2.14:1 しかなく、白文字が沈む）。 */
    $c_btn_bg   = eaf_opt('color_btn_bg', '') ?: '#e65100';
    $c_title    = eaf_opt('color_title', '#1f6feb');
    $c_badge    = eaf_opt('color_badge', '#ff5a36');
    $c_brand_rgb = eaf_hex_to_rgb($c_brand);

    $nonce   = wp_create_nonce('eamber_form');
    $ajax    = admin_url('admin-ajax.php');
    $privacy = eaf_opt('privacy_url');
    $terms   = eaf_opt('terms_url');
    /* 1ページに複数置かれる前提。uniqid() は同一リクエスト内で同じ値を返すことがあるため、
       連番を足して確実に一意にする（idが重なると label が別のフォームの入力欄を指してしまう）。
       CSSとJSは何個あっても最初の1回だけ出す。 */
    static $seq = 0, $assets_done = false;
    $uid     = 'fhs-' . uniqid() . '-' . (++$seq);
    $need_assets = !$assets_done;
    $assets_done = true;

    // compact では必須項目だけに絞る（メインビジュアル横などに収めるため）
    $cust_fields = eaf_visible_fields('customer',  eaf_customer_fields(),  $compact);
    $situ_fields = eaf_visible_fields('situation', eaf_situation_fields(), $compact);
    $show_note   = eaf_flag('show_note', false) && !$compact;
    $show_mkt    = eaf_flag('show_marketing', true) && !$compact;

    /* ステップは2つだけ:「お困りの内容（概要）→ ご連絡先（個人情報）」。
       画面を増やすほど離脱するため、聞くことは1画面目にまとめ、個人情報は必ず最後に置く。
       compact とティザーは元々短いので分けない。 */
    $stepped     = !$teaser && !$compact && eaf_flag('step_form', true);
    $step_titles = array('お困りの内容', 'ご連絡先');

    /* 第三者提供の設定は持たない（運営＝施工＝同じ会社の自社サイトに置くフォームのため）。
       同意文・利用目的は「自社が対応・連絡に使う」の一本に固定する。 */
    $op_name = eaf_opt('site_name', '当社');
    /* 急ぎのお客様には電話が最短なので、電話番号はフォームの一番上に出す
       （本気査定は「電話で済まされると申込が減る」ため伏せていたが、
        自社サイトの工事問い合わせでは電話も同じ受注。隠す理由がない）。 */
    $op_tel = eaf_opt('operator_contact', '');

    $ptype_options = '<option value="">選択してください</option>';
    foreach ($GLOBALS['EAF_PTYPE_LABEL'] as $k => $v) {
        $ptype_options .= '<option value="' . esc_attr($k) . '">' . esc_html($v) . '</option>';
    }

    $agree_label = 'プライバシーポリシーおよび免責事項に同意します（必須）';
    if ($privacy || $terms) {
        $p = $privacy ? '<a href="' . esc_url($privacy) . '" target="_blank" rel="noopener">プライバシーポリシー</a>' : 'プライバシーポリシー';
        $t = $terms ? '<a href="' . esc_url($terms) . '" target="_blank" rel="noopener">免責事項</a>' : '免責事項';
        $agree_label = $p . 'および' . $t . 'に同意します（必須）';
    }

    /** 工事内容のタイル選択（1タップで選べるようにする。セレクトより離脱が少ない） */
    $render_ptype_tiles = function ($uid, $name = 'ptype') {
        ob_start(); ?>
        <div class="fhs-tiles" role="group">
<?php foreach ($GLOBALS['EAF_PTYPE_LABEL'] as $k => $v):
        $short = isset($GLOBALS['EAF_PTYPE_SHORT'][$k]) ? $GLOBALS['EAF_PTYPE_SHORT'][$k] : $v;
        $tid = $uid . '-tile-' . $k; ?>
          <input type="radio" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($tid); ?>" value="<?php echo esc_attr($k); ?>" class="fhs-tile-input">
          <label class="fhs-tile" for="<?php echo esc_attr($tid); ?>"><?php echo esc_html($short); ?></label>
<?php endforeach; ?>
        </div>
<?php return ob_get_clean();
    };

    /** ティザー1項目ぶんのHTML（STEP表記つき） */
    $render_teaser_field = function ($key, $fd, $i, $uid) use ($render_ptype_tiles, $t_steps) {
        $nm = eaf_teaser_form_name($key);
        $id = $uid . '-t-' . $key;
        ob_start(); ?>
        <div class="fhs-tfield fhs-tfield-<?php echo esc_attr($key); ?>">
          <label<?php echo $fd['type'] === 'ptype' ? '' : ' for="' . esc_attr($id) . '"'; ?>>
<?php if ($t_steps): ?><span class="fhs-step">STEP <?php echo (int)$i; ?></span><?php endif; ?>
            <?php echo esc_html($fd['label']); ?>
          </label>
<?php if ($fd['type'] === 'ptype'): ?>
          <?php echo $render_ptype_tiles($uid, 'ptype'); ?>
<?php elseif ($fd['type'] === 'select'): ?>
          <select name="<?php echo esc_attr($nm); ?>" id="<?php echo esc_attr($id); ?>" class="fhs-typed">
            <option value="">選択してください</option>
<?php foreach (eaf_opt_list($fd['opts']) as $o): ?>
            <option value="<?php echo esc_attr($o); ?>"><?php echo esc_html($o); ?></option>
<?php endforeach; ?>
          </select>
<?php else: ?>
          <input type="text" name="<?php echo esc_attr($nm); ?>" id="<?php echo esc_attr($id); ?>" class="fhs-typed" placeholder="<?php echo esc_attr(isset($fd['ph']) ? $fd['ph'] : ''); ?>">
<?php endif; ?>
        </div>
<?php return ob_get_clean();
    };

    /** 1項目ぶんのHTML（ラベル＋入力欄）。$prefix はPOSTキーの接頭辞 */
    $render_field = function ($fd, $prefix, $uid) {
        $nm   = $prefix . $fd['key'];
        $req  = ($fd['mode'] === 'req');
        $full = ($fd['type'] === 'textarea');
        $id   = $uid . '-' . $nm;
        ob_start(); ?>
        <div class="fhs-field<?php echo $full ? ' fhs-full' : ''; ?>">
<?php if ($fd['type'] === 'check'): ?>
          <div class="fhs-check">
            <input type="checkbox" name="<?php echo esc_attr($nm); ?>" id="<?php echo esc_attr($id); ?>" value="1">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($fd['chk']); ?></label>
          </div>
<?php else: ?>
          <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($fd['label']); ?><?php echo $req ? '<span class="fhs-req">必須</span>' : '<span class="fhs-opt">任意</span>'; ?></label>
<?php if ($fd['type'] === 'select'): ?>
          <select name="<?php echo esc_attr($nm); ?>" id="<?php echo esc_attr($id); ?>" class="fhs-typed" data-req="<?php echo $req ? '1' : ''; ?>">
            <option value="">選択してください</option>
<?php foreach (eaf_opt_list($fd['opts']) as $o): ?>
            <option value="<?php echo esc_attr($o); ?>"><?php echo esc_html($o); ?></option>
<?php endforeach; ?>
          </select>
<?php elseif ($fd['type'] === 'textarea'): ?>
          <textarea name="<?php echo esc_attr($nm); ?>" id="<?php echo esc_attr($id); ?>" class="fhs-typed" data-req="<?php echo $req ? '1' : ''; ?>" rows="2" placeholder="<?php echo esc_attr(isset($fd['ph']) ? $fd['ph'] : ''); ?>"></textarea>
<?php else:
        /* 数値は type="number" にしない。全角数字を打つとブラウザが値を空にしてしまい、
           「入力したのに必須エラーが出る」状態になるため。inputmode でキーパッドだけ出す。 */
        $type = 'text'; $extra = '';
        if ($fd['type'] === 'number') { $extra = ' inputmode="decimal"'; }
        elseif ($fd['type'] === 'tel') { $type = 'tel'; $extra = ' inputmode="tel" autocomplete="tel"'; }
        elseif ($fd['key'] === 'name') { $extra = ' autocomplete="name"'; }
?>
          <input type="<?php echo $type; ?>"<?php echo $extra; ?> name="<?php echo esc_attr($nm); ?>" id="<?php echo esc_attr($id); ?>" class="fhs-typed" data-req="<?php echo $req ? '1' : ''; ?>" placeholder="<?php echo esc_attr(isset($fd['ph']) ? $fd['ph'] : ''); ?>">
<?php endif; ?>
<?php endif; ?>
        </div>
<?php return ob_get_clean();
    };

    ob_start(); ?>
<div class="fhs-wrap fhs-design-<?php echo esc_attr($design); ?>" id="<?php echo esc_attr($uid); ?>"
  data-fhs-teaser="<?php echo $teaser ? '1' : ''; ?>" data-fhs-target="<?php echo esc_attr($t_target); ?>"<?php
  echo $t_width ? ' style="max-width:' . esc_attr($t_width) . '"' : ''; ?>>
<?php if ($need_assets): ?>
  <style>
    .fhs-wrap{--fhs-brand:<?php echo esc_attr($c_brand); ?>;--fhs-brand-rgb:<?php echo esc_attr($c_brand_rgb); ?>;--fhs-btn-text:<?php echo esc_attr($c_btn_text); ?>;--fhs-btn-bg:<?php echo esc_attr($c_btn_bg); ?>;--fhs-title:<?php echo esc_attr($c_title); ?>;--fhs-badge-bg:<?php echo esc_attr($c_badge); ?>;--fhs-ink:#1a1f36;--fhs-muted:#6b7280;--fhs-line:#e5e7eb;width:100%;max-width:none;margin:0;color:var(--fhs-ink);font-family:inherit;line-height:1.75;font-size:17px}
    /* テーマ側が box-sizing を当てているかどうかで、余白ぶん高さ・幅がずれる。
       このフォームの中だけは border-box に固定して、どのテーマでも同じ見た目にする。 */
    .fhs-wrap,.fhs-wrap *{box-sizing:border-box}
    .fhs-card{background:transparent;border:0;border-radius:0;padding:0 0 28px}
    .fhs-wrap{overflow-wrap:anywhere}   /* 長いメールアドレスや住所で横スクロールさせない */
    .fhs-card > :last-child{margin-bottom:0}
    .fhs-wrap label{display:block;font-weight:700;margin:18px 0 7px;font-size:17px;color:#374151;letter-spacing:.01em}
    .fhs-req,.fhs-opt{font-size:11px;font-weight:700;border-radius:4px;padding:4px 7px;line-height:1;margin-left:8px;display:inline-flex;align-items:center;vertical-align:middle;letter-spacing:.02em;white-space:nowrap;flex:0 0 auto}
    .fhs-req{background:var(--fhs-badge-bg);color:#fff}
    .fhs-opt{background:#eef1f5;color:#6b7280}
    .fhs-req.fhs-done{background:var(--fhs-brand);color:#fff;border-radius:50%;width:20px;height:20px;padding:0;font-size:12px;justify-content:center}
    .fhs-lead{background:#f6f8fa;border:1px solid var(--fhs-line);border-radius:10px;padding:14px 16px;font-size:16px;color:#374151;margin-bottom:20px;white-space:pre-line}
    /* フォーム冒頭の電話案内。急ぎの読者が最初に目にする位置に置く */
    .fhs-telbar{background:rgba(var(--fhs-brand-rgb),.07);border:1px solid rgba(var(--fhs-brand-rgb),.22);border-radius:10px;padding:13px 16px;font-size:16px;font-weight:700;color:var(--fhs-ink);margin-bottom:16px;text-align:center}
    .fhs-telbar a{color:var(--fhs-brand);font-size:19px;text-decoration:none;white-space:nowrap}
    .fhs-section{display:flex;align-items:center;font-weight:800;font-size:21px;color:var(--fhs-ink);margin:34px 0 10px;padding-left:12px;border-left:5px solid var(--fhs-brand);line-height:1.45;letter-spacing:.01em}
    .fhs-form > .fhs-section:first-child{margin-top:0}
    /* ★チェックボックス・ラジオは対象外にする。padding や角丸が乗ると、
       テーマが自前で描いている環境で丸く潰れるなど表示が壊れる。 */
    .fhs-wrap input:not([type=checkbox]):not([type=radio]),.fhs-wrap select,.fhs-wrap textarea{width:100%;padding:14px 15px;border:1px solid #cbd5e1;border-radius:9px;font-size:18px;background:#fff;box-sizing:border-box;transition:border-color .15s,box-shadow .15s}
    .fhs-wrap input:not([type=checkbox]):not([type=radio]):focus,.fhs-wrap select:focus,.fhs-wrap textarea:focus{outline:none;border-color:var(--fhs-brand);box-shadow:0 0 0 3px rgba(var(--fhs-brand-rgb),.15)}
    /* テーマ側の appearance:none などを打ち消して、ブラウザ標準の四角いチェックに戻す */
    .fhs-wrap input[type=checkbox]{-webkit-appearance:checkbox;appearance:auto;width:auto;min-width:0;height:auto;padding:0;margin:0;border:0;border-radius:0;background:none;box-shadow:none}
    .fhs-wrap input[type=radio]{-webkit-appearance:radio;appearance:auto;width:auto;min-width:0;height:auto;padding:0;margin:0;border:0;border-radius:0;background:none;box-shadow:none}
    /* 項目は2カラムでコンパクトに（textarea・チェックは全幅） */
    .fhs-group{display:grid;grid-template-columns:1fr 1fr;gap:0 18px;align-items:start}
    .fhs-field{min-width:0}
    .fhs-field.fhs-full{grid-column:1/-1}
    .fhs-field > label:first-child{margin-top:16px}
    @media(max-width:560px){.fhs-group{grid-template-columns:1fr}}
    .fhs-hint{color:var(--fhs-muted);font-size:14px;margin-top:5px;line-height:1.7}
    .fhs-check{display:flex;gap:9px;align-items:flex-start;margin-top:14px}
    .fhs-wrap .fhs-check input[type=checkbox]{margin-top:5px;transform:scale(1.25);flex:0 0 auto}
    .fhs-check label{margin:0;font-weight:400;font-size:16px}
    .fhs-wrap button{margin-top:24px;width:100%;background:var(--fhs-btn-bg);color:var(--fhs-btn-text);border:0;border-radius:10px;padding:18px;font-size:20px;font-weight:700;cursor:pointer}
    /* ステップ表示。★.fhs-step はティザーの「STEP 1」バッジが使っているので別名にすること */
    .fhs-wrap .fhs-formstep{display:block}
    .fhs-steps{margin-bottom:22px}
    .fhs-stepbar{display:flex;gap:6px}
    .fhs-stepdot{flex:1;height:6px;border-radius:3px;background:#e5e7eb;transition:background .25s}
    .fhs-stepdot.is-on{background:var(--fhs-brand)}
    .fhs-stepnow{margin-top:9px;font-size:14px;font-weight:700;color:var(--fhs-muted)}
    .fhs-stepnow b{color:var(--fhs-brand)}
    .fhs-nav{display:flex;gap:12px;align-items:stretch}
    .fhs-nav button{margin-top:24px}
    .fhs-wrap .fhs-back{flex:0 0 34%;background:#fff;color:var(--fhs-muted);border:1px solid #cbd5e1;font-size:17px}
    .fhs-wrap .fhs-back:hover{filter:none;background:#f6f8fa}
    @media(max-width:480px){.fhs-wrap .fhs-back{flex:0 0 38%;font-size:15px;padding:14px 8px}}
    .fhs-wrap button:hover{filter:brightness(.93)}
    .fhs-wrap button:disabled{opacity:.6;cursor:wait;filter:none}
    /* ハニーポット：display:none だと一部のボットに読まれるため画面外へ逃がす */
    .fhs-hp{position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden}
    .fhs-privacy-note{background:#f6f8fa;border:1px solid var(--fhs-line);border-radius:9px;padding:13px 15px;font-size:14px;color:#4b5563;line-height:1.75;margin-top:16px}
    .fhs-err{background:#fdecea;border:1px solid #f5c6cb;color:#c0392b;padding:10px 12px;border-radius:9px;margin-bottom:10px;font-size:16px}
    .fhs-spec{width:100%;border-collapse:collapse;margin:16px 0;font-size:17px}
    .fhs-spec th,.fhs-spec td{border-bottom:1px solid var(--fhs-line);padding:12px 10px;text-align:left}
    .fhs-spec th{color:var(--fhs-muted);font-weight:600;width:38%}
    .fhs-ok{color:#0a7d33;font-weight:600;font-size:16px;margin-top:16px}
    .fhs-next-note{background:#eef6ff;border:1px solid #cfe3ff;border-radius:10px;padding:14px 16px;font-size:15px;color:#1c3d5a;margin-top:16px;line-height:1.8}

    /* デザイン: compact */
    .fhs-design-compact{max-width:440px}
    .fhs-design-compact .fhs-card{background:#fff;border:1px solid var(--fhs-line);border-radius:14px;padding:20px 18px;box-shadow:0 8px 28px rgba(16,24,40,.10)}
    .fhs-design-compact label{font-size:16px;margin:12px 0 5px}
    .fhs-design-compact input,.fhs-design-compact select,.fhs-design-compact textarea{padding:11px 12px;font-size:16px}
    .fhs-design-compact button{margin-top:16px;padding:14px;font-size:17px}
    .fhs-design-compact .fhs-form .fhs-hint{display:none}
    .fhs-design-compact .fhs-group{grid-template-columns:1fr} /* 幅が狭いので1カラム */
    .fhs-design-compact .fhs-section{display:none}
    .fhs-design-compact .fhs-check label{font-size:14px}
    .fhs-design-compact .fhs-lead{font-size:14px;padding:10px 12px}
    .fhs-design-compact .fhs-spec{font-size:15px}
    .fhs-design-compact .fhs-spec th,.fhs-design-compact .fhs-spec td{padding:9px 8px}

    /* 次に入力すべき欄をハイライト */
    .fhs-wrap select.fhs-next,.fhs-wrap input.fhs-next,.fhs-wrap textarea.fhs-next{border-color:rgba(var(--fhs-brand-rgb),.55);animation:fhsPulse 1.5s ease-in-out infinite}
    @keyframes fhsPulse{
      0%,100%{box-shadow:0 0 0 3px rgba(var(--fhs-brand-rgb),.16)}
      50%{box-shadow:0 0 0 7px rgba(var(--fhs-brand-rgb),.28)}
    }
    @media (prefers-reduced-motion:reduce){
      .fhs-wrap select.fhs-next,.fhs-wrap input.fhs-next,.fhs-wrap textarea.fhs-next{animation:none;box-shadow:0 0 0 3px rgba(var(--fhs-brand-rgb),.20)}
    }

    /* デザイン: card */
    .fhs-design-card .fhs-card{background:#fff;border:1px solid var(--fhs-line);border-radius:14px;padding:24px 22px;box-shadow:0 4px 18px rgba(16,24,40,.06)}
<?php // ここから下はティザー用。以降のスタイルも含めて、このブロックはページに1回だけ出力される ?>

    /* ============ ティザー（記事内などに置く入口フォーム） ============ */
    .fhs-design-teaser .fhs-card,.fhs-design-teaser-v .fhs-card{background:#fff;border:1px solid var(--fhs-line);border-radius:14px;padding:22px 22px 24px;box-shadow:0 8px 28px rgba(16,24,40,.10)}
    /* 記事の中に置くので中央寄せ。★幅(max-width)はラッパのインラインstyleで個別に指定する。
       ここに書くと、同じページに横長と縦を両方置いたとき、後から出力されたCSSが
       両方に効いてしまい、片方の幅が意図せず変わる。 */
    .fhs-design-teaser,.fhs-design-teaser-v{margin-left:auto;margin-right:auto}
    .fhs-design-teaser .fhs-hint,.fhs-design-teaser-v .fhs-hint{display:none}
    /* 見出しまわりは横長・縦で同じ組み方にする。
       1行目＝メリットのタグ、2行目＝バッジ＋アイコン＋見出し。
       HTMLの順（タグ→バッジ→見出し→小見出し）がそのまま表示順になるので order は使わない。 */
    .fhs-design-teaser .fhs-ttexts,.fhs-design-teaser-v .fhs-ttexts{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px 12px}
    .fhs-design-teaser .fhs-ttags,.fhs-design-teaser-v .fhs-ttags{flex:1 1 100%;margin-top:0;margin-bottom:2px}
    .fhs-design-teaser .fhs-tbadge-row,.fhs-design-teaser-v .fhs-tbadge-row{margin-bottom:0}
    .fhs-design-teaser .fhs-tsub,.fhs-design-teaser-v .fhs-tsub{flex:1 1 100%;margin-top:0}
    /* 横長は横幅が余るので、見出しを左・タグを右に振り分けて1行に収める。
       （HTMLの順はタグが先なので order で位置を入れ替える） */
    .fhs-design-teaser .fhs-thead{text-align:left}
    .fhs-design-teaser .fhs-ttexts{justify-content:flex-start}
    .fhs-design-teaser .fhs-tbadge-row{order:1}
    .fhs-design-teaser .fhs-ttitle{order:2}
    .fhs-design-teaser .fhs-ttags{order:3;flex:0 1 auto;margin-left:auto;margin-bottom:0}
    .fhs-design-teaser .fhs-tsub{order:4}
    .fhs-thead{text-align:center;padding-bottom:16px;margin-bottom:4px;border-bottom:1px solid var(--fhs-line)}
    .fhs-ttitle{font-size:22px;font-weight:800;color:var(--fhs-title);line-height:1.4;letter-spacing:.01em}
    .fhs-tsub{font-size:14px;color:var(--fhs-muted);margin-top:5px;line-height:1.6}
    /* 見出しの左に置くアイコン（会社ロゴ・ファビコンなど）。高さは見出しの文字に合わせる */
    /* 正方形なら高さ基準で収まり、横長ロゴでも読める程度の幅を許す（object-fitで縦横比は保つ） */
    .fhs-wrap .fhs-ticon{height:1.45em;width:auto;max-width:4.5em;vertical-align:-.22em;margin-right:.4em;display:inline-block;object-fit:contain}
    /* STEPバッジ */
    .fhs-step{display:inline-block;background:#3a4a5e;color:#fff;font-size:11px;font-weight:700;letter-spacing:.04em;border-radius:4px;padding:4px 8px;margin-right:9px;vertical-align:middle;line-height:1}
    .fhs-wrap .fhs-tfield > label{display:block;font-weight:700;font-size:16px;color:#374151;margin:16px 0 8px}
    /* 工事内容のタイル選択（1タップ） */
    /* ★セレクタは .fhs-wrap 付きで書くこと。上の .fhs-wrap input / .fhs-wrap label より
       詳細度が低いと width:100% や display:block に負け、隠したはずのラジオが
       画面幅いっぱいに広がって横スクロールが出る。 */
    /* タイルは3つ横並びが基本。入らなくなったら自動で2つ・1つに落ちる */
    .fhs-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:10px}
    .fhs-wrap .fhs-tile-input,.fhs-wrap input[type=radio].fhs-tile-input{position:absolute;opacity:0;width:1px;height:1px;padding:0;border:0;pointer-events:none;appearance:none;-webkit-appearance:none}
    .fhs-wrap .fhs-tile{display:flex;align-items:center;justify-content:center;text-align:center;background:#fff;border:2px solid #cbd5e1;border-radius:10px;padding:15px 8px;font-weight:700;font-size:16px;color:#374151;cursor:pointer;transition:border-color .15s,background .15s,color .15s;margin:0;line-height:1.3;min-height:56px}
    .fhs-wrap .fhs-tile:hover{border-color:rgba(var(--fhs-brand-rgb),.6)}
    .fhs-wrap .fhs-tile-input:checked + .fhs-tile{border-color:var(--fhs-brand);background:rgba(var(--fhs-brand-rgb),.08);color:var(--fhs-brand)}
    .fhs-wrap .fhs-tile-input:focus-visible + .fhs-tile{box-shadow:0 0 0 3px rgba(var(--fhs-brand-rgb),.25)}
    /* ===== 横長：入力欄を横一列に並べる =====
       ★ビューポート幅のメディアクエリではなく flex-wrap で折り返す。
         幅はショートコードの width で決まるので、画面が広くてもカードが狭いことがある。
         各項目に「これ以上は縮まない幅」を持たせ、入らなくなったら自動で下に落とす。 */
    .fhs-design-teaser .fhs-trow{display:flex;flex-wrap:wrap;gap:14px 22px;align-items:flex-start}
    .fhs-design-teaser .fhs-tfield{flex:1 1 240px;min-width:0}
    .fhs-design-teaser .fhs-tfield-ptype{flex:1.35 1 330px}   /* タイル3つぶん確保する */
    .fhs-design-teaser .fhs-tfield > label{margin-top:0}
    .fhs-design-teaser .fhs-tcta{flex:1 1 100%;display:flex;flex-direction:column;align-items:center}
    .fhs-design-teaser .fhs-tcta button{max-width:520px;margin-top:6px}
    /* 横長は見出しも1行にまとめる（バッジ＋見出しを横並び。狭ければ折り返す） */
    .fhs-design-teaser .fhs-ttexts{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px 14px}
    /* 見出しの上に置くバッジ（無料・秘密厳守など） */
    .fhs-tbadge-row{margin-bottom:9px;line-height:1}
    /* 見出しの横に並べるメリットのタグ */
    .fhs-ttags{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:9px}
    /* タグは四角・線なしの塗りだけ。バッジ（丸い塗り）と形を変えて、並んでもくどくならないようにする */
    .fhs-ttag{font-size:12px;font-weight:700;color:var(--fhs-brand);background:rgba(var(--fhs-brand-rgb),.10);border:0;border-radius:5px;padding:6px 11px;line-height:1.3;white-space:nowrap}
    .fhs-tbadge{display:inline-block;background:var(--fhs-badge-bg);border:1px solid var(--fhs-badge-bg);color:#fff;font-size:12px;font-weight:800;border-radius:999px;padding:5px 14px;line-height:1}
    .fhs-tnote{color:var(--fhs-muted);font-size:12px;margin-top:12px;line-height:1.8;text-align:center;text-wrap:pretty}
    .fhs-tnote span{display:block}
    .fhs-admin-warn{background:#fdecea;border:1px solid #f5c6cb;color:#c0392b;padding:12px 14px;border-radius:9px;font-size:14px;margin-bottom:12px;line-height:1.8}
    /* 自動で拾えている場合は「エラー」ではないので、色を落とす */
    .fhs-admin-warn.fhs-admin-note{background:#fff8e6;border-color:#f0e0a8;color:#6b5a12}
    .fhs-admin-warn code{background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;font-size:13px}
    @media(max-width:560px){
      /* 幅が無いときは横に振り分けず、中央に積む */
      .fhs-design-teaser .fhs-thead{text-align:center}
      .fhs-design-teaser .fhs-ttexts{justify-content:center}
      .fhs-design-teaser .fhs-ttags{order:0;flex:1 1 100%;margin-left:0;margin-bottom:2px;justify-content:center}
      .fhs-tiles{grid-template-columns:1fr;gap:8px}
      .fhs-wrap .fhs-tile{min-height:0;padding:13px 8px}
      .fhs-ttitle{font-size:19px}
      .fhs-design-teaser .fhs-card,.fhs-design-teaser-v .fhs-card{padding:18px 16px 20px}
      .fhs-wrap .fhs-ticon{max-width:3.4em}
    }

    /* 引き継ぎ後の「続きはこちらから」バナー */
    .fhs-resume{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 10px;background:rgba(var(--fhs-brand-rgb),.07);border:1px solid rgba(var(--fhs-brand-rgb),.22);border-left:4px solid var(--fhs-brand);border-radius:8px;padding:12px 14px;margin:26px 0 6px;font-size:15px}
    .fhs-resume b{color:var(--fhs-brand);font-weight:800}
    .fhs-resume span{color:var(--fhs-muted);font-size:14px}
  </style>
<?php endif; /* $need_assets */ ?>

  <div class="fhs-card fhs-form-card">
<?php if ($glued && current_user_can('manage_options')): ?>
    <div class="fhs-admin-warn fhs-admin-note"><strong>【この行は管理者にだけ見えています】ショートコードの属性の間に半角スペースが足りません。</strong><br>
      いまは自動で読み取って表示していますが、<code>"</code> と次の属性の間に<strong>半角スペース</strong>を入れてください。<br>
      × <code>url="/○○○/form/"width="640"</code>　→　○ <code>url="/○○○/form/" width="640"</code></div>
<?php endif; ?>
<?php if ($teaser): /* ===== 入口フォーム（ティザー）===== */ ?>
<?php if (!$t_target && current_user_can('manage_options')): ?>
    <div class="fhs-admin-warn"><strong>【この行は管理者にだけ見えています】</strong><br>
      ティザーの遷移先が決まっていません。次のどちらかで設定してください。<br>
      ① <a href="<?php echo esc_url(admin_url('admin.php?page=eamber-form')); ?>">設定 → 基本設定 → お問い合わせページ</a> で、<code>[eamber_form]</code> を貼ったページを選ぶ（<strong>おすすめ</strong>。以後どのティザーにも効きます）<br>
      ② このショートコードに <code>url="https://…/○○○/form/"</code> を追加する</div>
<?php endif; ?>
    <div class="fhs-errors"></div>
    <form class="fhs-form">
      <div class="fhs-thead">
        <div class="fhs-ttexts">
<?php /* タグを先に置く。縦ではこの順（タグ→バッジ→見出し）がそのまま見た目の順になり、
         読み上げの順序とも一致する。横長は1行に並べるので、CSSのorderで位置だけ入れ替える。 */ ?>
<?php if ($t_tags): ?>
          <div class="fhs-ttags">
<?php foreach ($t_tags as $tag): ?>
            <span class="fhs-ttag"><?php echo esc_html($tag); ?></span>
<?php endforeach; ?>
          </div>
<?php endif; ?>
<?php if ($t_badge !== ''): ?>
          <div class="fhs-tbadge-row"><span class="fhs-tbadge"><?php echo esc_html($t_badge); ?></span></div>
<?php endif; ?>
          <div class="fhs-ttitle"><?php if ($t_logo): ?><img class="fhs-ticon" src="<?php echo esc_url($t_logo); ?>" alt="<?php echo esc_attr(eaf_opt('site_name', '')); ?>"><?php endif; ?><?php echo esc_html($t_title); ?></div>
<?php if ($t_sub !== ''): ?>
          <div class="fhs-tsub"><?php echo esc_html($t_sub); ?></div>
<?php endif; ?>
        </div>
      </div>
      <div class="fhs-trow">
<?php $t_reg = eaf_teaser_fields(); $ti = 1;
      foreach ($t_fields as $tk) { echo $render_teaser_field($tk, $t_reg[$tk], $ti++, $uid); } ?>
        <div class="fhs-tcta">
          <button class="fhs-submit" type="submit"><?php echo esc_html($btn); ?></button>
        </div>
      </div>
      <?php /* 注記は文ごとに行を分ける。1つの段落にすると幅によって
               「…送信されませ／ん。」のように中途半端な位置で折り返してしまう。
               note属性では | で行を区切れる。 */ ?>
      <div class="fhs-tnote">
<?php foreach ($t_note_lines as $ln): ?>
        <span><?php echo esc_html($ln); ?></span>
<?php endforeach; ?>
      </div>
      <?php /* ティザーには免責を置かない。ここでは価格を一切示さず、申し込みも受け付けない
               （次のページへ移るだけ）ため。免責が要るのは価格を示す場面と申し込みを受け付ける場面で、
               それは遷移先の本フォーム・完了画面・自動返信メールに必ず表示される。 */ ?>
    </form>
<?php else: /* ===== 通常のフォーム ===== */ ?>
<?php if ($op_tel !== ''): ?>
    <div class="fhs-telbar">お急ぎの方はお電話が早いです：<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $op_tel)); ?>"><?php echo esc_html($op_tel); ?></a></div>
<?php endif; ?>
<?php $lead = eaf_opt('lead_text'); if ($lead !== ''): ?>
    <div class="fhs-lead"><?php echo esc_html($lead); ?></div>
<?php endif; ?>
    <div class="fhs-errors"></div>
    <form class="fhs-form">
      <?php /* ボット対策。人には見えず、自動入力ツールだけが埋める欄 */ ?>
      <div class="fhs-hp" aria-hidden="true">
        <label for="<?php echo esc_attr($uid . '-website'); ?>">ウェブサイト（入力しないでください）</label>
        <input type="text" name="eaf_website" id="<?php echo esc_attr($uid . '-website'); ?>" tabindex="-1" autocomplete="off">
      </div>

<?php if ($stepped): /* 進み具合。ゴールが見えると最後まで書いてもらいやすい */ ?>
      <div class="fhs-steps">
        <div class="fhs-stepbar">
<?php for ($i = 1; $i <= count($step_titles); $i++): ?>
          <span class="fhs-stepdot" data-step="<?php echo $i; ?>"></span>
<?php endfor; ?>
        </div>
        <div class="fhs-stepnow"></div>
      </div>
<?php endif; ?>

<?php if ($stepped): ?><div class="fhs-formstep" data-step="1"><?php endif; ?>
      <div class="fhs-section">お困りの内容</div>
      <label for="<?php echo esc_attr($uid . '-ptype'); ?>">工事内容<span class="fhs-req">必須</span></label>
      <select name="ptype" id="<?php echo esc_attr($uid . '-ptype'); ?>" required><?php echo $ptype_options; ?></select>
      <div class="fhs-hint">選ぶと、その内容に合わせた入力欄が表示されます</div>

      <label for="<?php echo esc_attr($uid . '-address'); ?>">お住まい・現場の市町村<span class="fhs-req">必須</span></label>
      <select name="address" id="<?php echo esc_attr($uid . '-address'); ?>" required>
        <option value="">選択してください</option>
<?php foreach (eaf_opt_list('city') as $ct): ?>
        <option value="<?php echo esc_attr($ct); ?>"><?php echo esc_html($ct); ?></option>
<?php endforeach; ?>
      </select>
      <div class="fhs-hint">番地までは不要です。詳しい場所は折り返しの際にうかがいます</div>

<?php foreach (eaf_property_fields() as $pt => $flds):
        $vis = eaf_visible_fields('prop_' . $pt, $flds, $compact);
        if (!$vis) continue; ?>
      <div class="fhs-group" data-ptype="<?php echo esc_attr($pt); ?>" style="display:none">
<?php   foreach ($vis as $fd) { echo $render_field($fd, $pt . '__', $uid); } ?>
      </div>
<?php endforeach; ?>
<?php /* ご状況は最初のステップに同居させる（ステップは「概要 → 個人情報」の2つだけ） */ ?>
<?php if ($situ_fields): ?>
      <div class="fhs-group">
<?php   foreach ($situ_fields as $fd) { echo $render_field($fd, 'situation_', $uid); } ?>
      </div>
<?php endif; ?>

<?php if ($show_note): ?>
      <label for="<?php echo esc_attr($uid . '-note'); ?>">備考・ご要望<span class="fhs-opt">任意</span></label>
      <textarea name="note_text" id="<?php echo esc_attr($uid . '-note'); ?>" rows="2" placeholder="ご不明な点、ご希望などがあればご記入ください"></textarea>
<?php endif; ?>
<?php if ($stepped): ?></div><?php endif; /* step 1 ここまで */ ?>

<?php if ($stepped): ?><div class="fhs-formstep" data-step="2" style="display:none"><?php endif; ?>
      <div class="fhs-section">ご連絡先</div>
<?php if ($cust_fields): ?>
      <div class="fhs-group">
<?php   foreach ($cust_fields as $fd) { echo $render_field($fd, 'customer_', $uid); } ?>
      </div>
<?php endif; ?>
      <label for="<?php echo esc_attr($uid . '-email'); ?>">メールアドレス<span class="fhs-opt">任意</span></label>
      <input type="email" name="email" id="<?php echo esc_attr($uid . '-email'); ?>" placeholder="you@example.com" autocomplete="email">
      <div class="fhs-hint">ご入力いただくと、受付内容の控えをメールでお送りします</div>

      <?php /* 個人情報の利用目的の明示（個情法21条）。同意を求める直前に必ず出す。
               プライバシーポリシーURLが未設定でも、最低限ここで目的が伝わるようにしておく。 */ ?>
      <div class="fhs-privacy-note">
        <strong>個人情報の取り扱いについて</strong><br>
        ご入力いただいた内容は、<?php echo esc_html($op_name); ?>が<strong>お問い合わせへの対応とご連絡、およびそれに関するご案内</strong>のために利用します。<br>
        ご本人の同意なく第三者に提供することはありません。<br>
        削除をご希望の場合は、<?php echo $privacy
            ? '<a href="' . esc_url($privacy) . '" target="_blank" rel="noopener">プライバシーポリシー</a>に記載の窓口'
            : '当社の窓口'; ?>までお申し付けください。
      </div>

      <div class="fhs-check">
        <input type="checkbox" name="agree" id="<?php echo esc_attr($uid . '-agree'); ?>" value="1" required>
        <label for="<?php echo esc_attr($uid . '-agree'); ?>"><?php echo $agree_label; ?></label>
      </div>
<?php if ($show_mkt): ?>
      <div class="fhs-check">
        <input type="checkbox" name="marketing" id="<?php echo esc_attr($uid . '-mkt'); ?>" value="1">
        <label for="<?php echo esc_attr($uid . '-mkt'); ?>">工事・メンテナンスに関するご案内やお役立ち情報のメール受け取りを希望します（任意）</label>
      </div>
<?php endif; ?>
<?php if ($compact): ?>
      <input type="hidden" name="compact" value="1">
<?php endif; ?>
<?php if ($stepped): ?></div><?php endif; /* 最終ステップ ここまで */ ?>

      <div class="fhs-nav">
<?php if ($stepped): ?>
        <button type="button" class="fhs-back" style="display:none">← 戻る</button>
        <button type="button" class="fhs-nextstep">次へ進む</button>
<?php endif; ?>
        <button class="fhs-submit" type="submit"<?php echo $stepped ? ' style="display:none"' : ''; ?>><?php echo esc_html($btn); ?></button>
      </div>
    </form>
<?php endif; /* ===== 分岐ここまで ===== */ ?>

<?php /* 会社紹介の欄はあえて置かない。このフォームは自社サイトに置く前提で、
         「どこの会社か」はサイト自体が示している（本気査定のような
         運営と査定会社が別のケースではないため）。 */ ?>
  </div>

  <div class="fhs-card fhs-result" style="display:none"></div>
</div>

<?php if ($need_assets): ?>
<script>
(function(){
  /* このスクリプトはページに1回だけ出力し、ページ内のフォームを全部まとめて初期化する。
     LPのようにティザーを何個も置いても、重いJSが人数分ぶら下がらないようにするため。 */
  if (window.fhsFormsReady) return;
  window.fhsFormsReady = true;

  var AJAX = <?php echo wp_json_encode($ajax); ?>;
  var NONCE = <?php echo wp_json_encode($nonce); ?>;
  var LOADED_AT = Date.now();   // ページキャッシュがあってもJS側で計測すれば正しく効く
  var HANDOFF_KEY = 'eaf_handoff';

  /* ★ティザーからの引き継ぎは sessionStorage で行う（URLのクエリには載せない）。
     入力内容を URL に載せると、ブラウザの履歴や
     外部サイトへのリファラに残ってしまうため。
     サーバー側でHTMLに焼き込む方式も採らない（ページキャッシュが効くと
     「最初に開いた人の入力値」が他の訪問者にも配られてしまう）。 */
  function readHandoff(){
    try {
      var raw = sessionStorage.getItem(HANDOFF_KEY);
      if (!raw) return null;
      var o = JSON.parse(raw);
      return (o && typeof o === 'object') ? o : null;
    } catch (e) { return null; }   // 使えない環境では引き継ぎ無しとして扱う
  }

  function init(wrap){
  if (!wrap || wrap.getAttribute('data-fhs-init')) return;
  wrap.setAttribute('data-fhs-init', '1');
  var TEASER = wrap.getAttribute('data-fhs-teaser') === '1';
  var TARGET = wrap.getAttribute('data-fhs-target') || '';
  var form = wrap.querySelector('.fhs-form'), errBox = wrap.querySelector('.fhs-errors');
  if (!form) return;
  var formCard = wrap.querySelector('.fhs-form-card'), resultCard = wrap.querySelector('.fhs-result');
  var btn = wrap.querySelector('.fhs-submit');
  var SUBMIT_LABEL = btn ? btn.textContent : '送信';
  var SENDING = false;   // 送信中フラグ（二重送信の防止。btn.disabled だけではEnter連打を防げない）
  var ptypeSel = wrap.querySelector('select[name="ptype"]');
  var groups = wrap.querySelectorAll('.fhs-group[data-ptype]');

  /* その欄が「ちゃんと埋まっているか」。
     ★空でないだけで✓を出すと、「090」だけでもチェックが付いてしまい、
       送信して初めて形式エラーが出る。見た目と結果が食い違うので形式まで見る。
     戻り値: null=OK / 'empty'=未入力 / 'format'=形式が違う */
  function fieldProblem(el){
    var v = String(el.value == null ? '' : el.value).trim();
    if (v === '') return 'empty';
    var type = (el.getAttribute('type') || '').toLowerCase();
    var mode = (el.getAttribute('inputmode') || '').toLowerCase();
    var han = v.replace(/[０-９]/g, function(c){ return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); })
               .replace(/[－ーｰ−—]/g, '-').replace(/[．]/g, '.');
    if (type === 'tel') {
      var digits = han.replace(/[^0-9]/g, '');
      return (digits.length >= 9 && digits.length <= 11) ? null : 'format';   // サーバー側と同じ基準
    }
    if (type === 'email') {
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? null : 'format';
    }
    if (mode === 'decimal') {                       // 面積・築年などの数値欄
      return /^[0-9]+(\.[0-9]+)?$/.test(han) ? null : 'format';
    }
    return null;
  }
  function fieldOk(el){ return fieldProblem(el) === null; }

  // 直前の <label> 内の「必須」バッジ
  function badgeFor(el){
    var lbl = el.previousElementSibling;
    return (lbl && lbl.tagName === 'LABEL') ? lbl.querySelector('.fhs-req') : null;
  }

  // 画面の並び順に：工事内容 → 市町村 → 選択中の内容の必須項目 → その他の必須項目
  // ★メールは任意なのでここには入れない（形式チェックはステップ送りと送信時に行う）
  function currentRequired(){
    var req = [];
    if (form.elements['ptype'])   req.push(form.elements['ptype']);
    if (form.elements['address']) req.push(form.elements['address']);
    var pt = ptypeSel ? ptypeSel.value : '';
    if (pt) {
      var g = wrap.querySelector('.fhs-group[data-ptype="' + pt + '"]');
      if (g) Array.prototype.forEach.call(g.querySelectorAll('[data-req="1"]'), function(el){ req.push(el); });
    }
    Array.prototype.forEach.call(form.querySelectorAll('.fhs-group:not([data-ptype]) [data-req="1"]'), function(el){ req.push(el); });
    return req;
  }

  var resumeBox = null;

  function updateFormState(){
    if (TEASER) return;   // ティザーは引き継ぐだけなので、必須ガイドは動かさない
    Array.prototype.forEach.call(form.querySelectorAll('.fhs-next'), function(e){ e.classList.remove('fhs-next'); });
    var req = currentRequired(), firstEmpty = null, remaining = 0;
    req.forEach(function(el){
      var b = badgeFor(el);
      var filled = fieldOk(el);   // 形式まで正しいときだけ ✓ にする
      if (b) {
        if (filled) { b.classList.add('fhs-done'); b.textContent = '✓'; }
        else { b.classList.remove('fhs-done'); b.textContent = '必須'; }
      }
      if (!filled) { remaining++; if (!firstEmpty) firstEmpty = el; }
    });
    if (firstEmpty) firstEmpty.classList.add('fhs-next');
    if (resumeBox) {
      if (remaining === 0) resumeBox.style.display = 'none';
      else { resumeBox.style.display = ''; resumeBox.querySelector('span').textContent = 'あと' + remaining + '項目で完了です'; }
    }
  }

  // 種別を選ぶと、その種別の入力欄だけ表示
  function switchType(){
    if (TEASER) return;
    var pt = ptypeSel ? ptypeSel.value : '';
    Array.prototype.forEach.call(groups, function(g){
      g.style.display = (g.getAttribute('data-ptype') === pt) ? '' : 'none';
    });
    updateFormState();
  }

  /* 引き継ぎで来たときだけ出す「↓ 続きはこちらから」。
     長いフォームの途中に飛ばされると、どこから書けばいいのか分からなくなるため。 */
  function insertResumeBanner(){
    var el = wrap.querySelector('.fhs-next');
    if (!el) return;
    var anchor = el;
    while (anchor && anchor.parentNode !== form) anchor = anchor.parentNode;
    if (!anchor) return;
    var prev = anchor.previousElementSibling;
    if (prev && prev.tagName === 'LABEL') anchor = prev;
    resumeBox = document.createElement('div');
    resumeBox.className = 'fhs-resume';
    resumeBox.innerHTML = '<b>↓ 続きはこちらから</b><span></span>';
    form.insertBefore(resumeBox, anchor);
    updateFormState();
  }

  /* ===== ステップ表示 =====
     一画面に全部出すより離脱が減る。個人情報は必ず最後のステップに置く。
     ページ遷移はしない（読み込み待ちで離脱するため、表示の切り替えだけで済ませる）。 */
  var steps = wrap.querySelectorAll('.fhs-formstep');
  var STEPPED = steps.length > 1;
  var stepNow = 0;
  var stepDots = wrap.querySelectorAll('.fhs-stepdot');
  var stepLabel = wrap.querySelector('.fhs-stepnow');
  var backBtn = wrap.querySelector('.fhs-back');
  var nextBtn = wrap.querySelector('.fhs-nextstep');
  var STEP_TITLES = <?php echo wp_json_encode($stepped ? $step_titles : array()); ?>;

  /** そのステップの中で、まだ埋まっていない必須項目を返す */
  function missingIn(i){
    if (!STEPPED) return [];
    var box = steps[i], out = [];
    var els = box.querySelectorAll('select[name="ptype"], select[name="address"], [data-req="1"]');
    Array.prototype.forEach.call(els, function(el){
      if (!el.offsetParent && el.type !== 'hidden') return;      // 表示されていない種別の欄は対象外
      if (el.closest('.fhs-group[data-ptype]') && el.closest('.fhs-group[data-ptype]').style.display === 'none') return;
      if (!fieldOk(el)) out.push(el);
    });
    // メールは任意。ただし入力があって形式が違うときだけ止める
    var em = box.querySelector('input[name="email"]');
    if (em && em.value.trim() !== '' && !fieldOk(em)) out.push(em);
    var agree = box.querySelector('input[name="agree"]');
    if (agree && !agree.checked) out.push(agree);
    return out;
  }

  function labelTextOf(el){
    if (el.name === 'agree') return '個人情報の取扱いへの同意';
    var lbl = el.previousElementSibling;
    if (!lbl || lbl.tagName !== 'LABEL') {
      var byId = el.id ? wrap.querySelector('label[for="' + el.id + '"]') : null;
      lbl = byId || lbl;
    }
    if (!lbl) return 'この項目';
    return lbl.textContent.replace(/必須|任意|✓/g, '').trim();
  }

  function showStep(i){
    if (!STEPPED) return;
    stepNow = Math.max(0, Math.min(steps.length - 1, i));
    Array.prototype.forEach.call(steps, function(s, n){ s.style.display = (n === stepNow) ? '' : 'none'; });
    Array.prototype.forEach.call(stepDots, function(d, n){ d.classList.toggle('is-on', n <= stepNow); });
    if (stepLabel) stepLabel.innerHTML = 'STEP <b>' + (stepNow + 1) + '</b> / ' + steps.length + '　' + esc(STEP_TITLES[stepNow] || '');
    var last = (stepNow === steps.length - 1);
    if (nextBtn) nextBtn.style.display = last ? 'none' : '';
    if (btn)     btn.style.display     = last ? '' : 'none';
    if (backBtn) backBtn.style.display = (stepNow === 0) ? 'none' : '';
    errBox.innerHTML = '';
    updateFormState();
  }

  if (STEPPED) {
    if (nextBtn) nextBtn.addEventListener('click', function(){
      var miss = missingIn(stepNow);
      if (miss.length) {
        errBox.innerHTML = miss.map(function(el){
          var why = fieldProblem(el);
          var name = esc(labelTextOf(el));
          var msg = (why === 'format')
            ? '「' + name + '」の形式をご確認ください。'
            : '「' + name + '」を入力してください。';
          return '<div class="fhs-err">' + msg + '</div>';
        }).join('');
        errBox.scrollIntoView({ behavior:'smooth', block:'center' });
        if (miss[0].focus) miss[0].focus();
        return;
      }
      showStep(stepNow + 1);
      wrap.scrollIntoView({ behavior:'smooth', block:'start' });
    });
    if (backBtn) backBtn.addEventListener('click', function(){
      showStep(stepNow - 1);
      wrap.scrollIntoView({ behavior:'smooth', block:'start' });
    });
    showStep(0);
  }

  function scrollToFirstEmpty(){
    var target = resumeBox || wrap.querySelector('.fhs-typed.fhs-next, input.fhs-next, select.fhs-next') || btn;
    if (!target) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(function(){
      target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    }, 120);
  }

  if (ptypeSel) ptypeSel.addEventListener('change', switchType);
  Array.prototype.forEach.call(form.querySelectorAll('.fhs-typed, select[name="address"], input[name="email"]'), function(el){
    el.addEventListener('change', updateFormState);
    el.addEventListener('input', updateFormState);
  });

  switchType(); // 初期表示
  /* ブラウザバック等でブラウザが選択を復元しても change は発火しないため、
     「エアコンと表示されているのに入力欄が出ない」状態になる。読み込み時と
     bfcache 復帰時に自前で反映し直す。 */
  setTimeout(switchType, 0);
  setTimeout(switchType, 250);
  window.addEventListener('pageshow', function(e){ if (e.persisted) switchType(); });

  /* ティザーから引き継いだ入力値を復元する */
  if (!TEASER) {
    var HANDOFF = readHandoff();
    if (HANDOFF && Object.keys(HANDOFF).length) {
      var applyHandoff = function(){
        Object.keys(HANDOFF).forEach(function(n){
          var el = form.elements[n];
          if (el && HANDOFF[n]) el.value = HANDOFF[n];
        });
        switchType();
      };
      applyHandoff();
      /* ブラウザの自動入力は、こちらの復元より後に走って値を上書きすることがある。
         戻る操作（bfcache）でも以前の入力値が復元される。引き継いだ値が正しいので取り戻す。 */
      setTimeout(applyHandoff, 0);
      setTimeout(applyHandoff, 250);
      window.addEventListener('pageshow', function(e){ if (e.persisted) applyHandoff(); });

      if (STEPPED) {
        /* 引き継ぎで埋まったステップは飛ばして、最初に書くところから始める */
        var start = steps.length - 1;
        for (var si = 0; si < steps.length; si++) {
          if (missingIn(si).length) { start = si; break; }
        }
        showStep(start);
        if (start > 0) scrollToFirstEmpty();
      } else {
        insertResumeBanner();
        scrollToFirstEmpty();
      }
    }
  }

  function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

  form.addEventListener('submit', function(e){
    e.preventDefault();

    /* ティザー: ここでは送信しない。入力値を持って本フォームのページへ移るだけ。 */
    if (TEASER) {
      var data = {};
      Array.prototype.forEach.call(form.querySelectorAll('.fhs-typed, .fhs-tile-input'), function(el){
        if (el.type === 'radio') { if (el.checked && el.value) data[el.name] = el.value; }
        else if (el.value) data[el.name] = el.value;
      });
      try { sessionStorage.setItem(HANDOFF_KEY, JSON.stringify(data)); } catch (err) { /* 引き継げないだけ */ }
      if (!TARGET) {
        errBox.innerHTML = '<div class="fhs-err">遷移先のページが設定されていません。サイト管理者にお知らせください。</div>';
        return;
      }
      window.location.href = TARGET;
      return;
    }

    if (SENDING) return;   // 送信中の再送信（連打・Enter連打）を止める
    SENDING = true;
    errBox.innerHTML = '';
    btn.disabled = true; btn.textContent = '送信中…';

    var fd = new FormData(form);
    fd.append('action', 'eamber_form');
    fd.append('nonce', NONCE);
    fd.append('eaf_elapsed', String(Date.now() - LOADED_AT));   // 表示から送信までの経過ms（ボット判定）

    /* ページキャッシュで古いnonceが配られていると 403 になる。
       その場合だけ新しいnonceを取り直して1回だけ送り直す。 */
    function send(retried){
      fd.set('nonce', NONCE);
      return fetch(AJAX, { method:'POST', body: fd, credentials:'same-origin' })
        .then(function(r){
          if (r.status === 403 && !retried) {
            return fetch(AJAX + '?action=eamber_form_nonce', { credentials:'same-origin' })
              .then(function(x){ return x.json(); })
              .then(function(n){
                if (!n || !n.nonce) throw new Error('nonce');
                NONCE = n.nonce;
                return send(true);
              });
          }
          return r.json();
        });
    }

    send(false)
      .then(function(d){
        SENDING = false;
        btn.disabled = false; btn.textContent = SUBMIT_LABEL;
        /* ★応答が正しい形か必ず確かめる。
           admin-ajax は失敗時に -1 や 0 という「JSONとして解釈できてしまう値」を返す。
           素通しすると、1件も保存されていないのに「受け付けました」と表示してしまう。 */
        if (!d || typeof d !== 'object' || (d.ok !== true && !d.errors)) throw new Error('bad-response');
        if (d.errors) {
          errBox.innerHTML = d.errors.map(function(x){return '<div class="fhs-err">'+esc(x)+'</div>';}).join('');
          errBox.scrollIntoView({ behavior:'smooth', block:'center' });   // 画面外だと「無反応」に見えて連打される
          return;
        }
        renderResult(d);
      })
      .catch(function(){
        SENDING = false;
        btn.disabled = false; btn.textContent = SUBMIT_LABEL;
        errBox.innerHTML = '<div class="fhs-err">通信エラーが発生しました。時間をおいて再度お試しください。</div>';
      });
  });

  function renderResult(d){
    // 申し込みが完了したら引き継ぎデータは用済み。残すと次の訪問で古い値が復元されてしまう
    try { sessionStorage.removeItem(HANDOFF_KEY); } catch (e) {}
    var rows = (d.name ? '<tr><th>お名前</th><td>'+esc(d.name)+' 様</td></tr>' : '')
      + (d.tel ? '<tr><th>電話番号</th><td>'+esc(d.tel)+'</td></tr>' : '')
      + (d.email ? '<tr><th>メール</th><td>'+esc(d.email)+'</td></tr>' : '')
      + '<tr><th>工事内容</th><td>'+esc(d.ptype_label)+'</td></tr>'
      + (d.address ? '<tr><th>現場の市町村</th><td>'+esc(d.address)+'</td></tr>' : '');
    var det = d.confirm_text
      ? '<div class="fhs-hint" style="white-space:pre-line;margin-top:10px">'+esc(d.confirm_text)+'</div>' : '';
    var mailLine = !d.email
      ? '<p class="fhs-hint">担当者よりお電話にて折り返しご連絡します。</p>'
      : (d.mail_ok
        ? '<p class="fhs-ok">✓ '+esc(d.email)+' 宛に受付完了メールをお送りしました。</p>'
        : '<p class="fhs-hint">お問い合わせは完了しています（確認メールの送信に失敗した可能性があります。担当より別途ご連絡します）。</p>');
    var html = '<h3 style="margin-top:0">お問い合わせを受け付けました</h3>'
      + '<div class="fhs-next-note"><strong>このあとの流れ</strong><br>担当者が内容を確認し、ご入力いただいたご連絡先へご連絡いたします。'
      + '営業時間の都合により、お時間をいただく場合があります。</div>'
      + '<table class="fhs-spec">'+rows+'</table>'
      + det
      + mailLine;
    resultCard.innerHTML = html;
    formCard.style.display = 'none';
    resultCard.style.display = 'block';
    resultCard.scrollIntoView({ behavior:'smooth', block:'start' });
  }
  }

  function initAll(){
    Array.prototype.forEach.call(document.querySelectorAll('.fhs-wrap'), init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
  // 戻る操作からの復帰や、後から差し込まれたフォームにも効かせる
  window.addEventListener('pageshow', initAll);
})();
</script>
<?php endif; /* $need_assets */ ?>
<?php
    return ob_get_clean();
}
