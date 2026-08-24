<?php
/**
 * スパム対策。
 * ★最重要は「まともな申し込みを弾かないこと」。取りこぼしの損失の方が大きい。
 */
$GLOBALS['FAKE_STATE_FILE'] = __DIR__ . '/spam_state.json';
@unlink($GLOBALS['FAKE_STATE_FILE']);
require __DIR__ . '/wp_stub.php';
require dirname(__DIR__) . '/eamber-form/eamber-form.php';

$ng = 0;
function t($n, $g, $w) {
    global $ng; $ok = ($g === $w); if (!$ok) $ng++;
    printf("%s %s (got=%s)\n", $ok ? 'OK  ' : 'NG  ', $n, var_export($g, true));
}
function set_opts($a) { update_option(EAF_OPT, eaf_sanitize_options($a)); }

/* ===== 1. まともな入力を弾かない（誤爆チェック） ===== */
set_opts(array('spam_block_link' => '1'));
$ok_inputs = array(
    array('山田 太郎'),
    array('ヤマダ タロウ'),
    array('岡山県岡山市北区大供1丁目1-1'),
    array('パークハイム東京 A棟 503号室'),
    array('2020年に水回りをリフォームしました。日当たりが良く、駅から徒歩5分です。'),
    array('相続で取得した物件です。兄弟3人で話し合っています。よろしくお願いします。'),
    array('株式会社ABC建設'),                    // 社名にアルファベット
    array('ハイツ ラ・メール 101'),               // カタカナ＋記号
    array('090-1234-5678'),                      // 電話番号
    array('taro.yamada@example.co.jp'),          // メールアドレス（@はリンクではない）
    array('土地の面積は約120㎡、間口8m×奥行15mです。'),
    array('R6年に外壁塗装済み（費用120万円）'),
);
foreach ($ok_inputs as $i => $v) {
    t('通す: ' . mb_substr($v[0], 0, 20), eaf_spam_hit($v), false);
}

/* ===== 2. スパムらしい入力を弾く ===== */
$spam_inputs = array(
    'リンク(http)'      => array('詳しくはこちら http://spam.example.com'),
    'リンク(https)'     => array('https://buy-now.example.com で稼げます'),
    'リンク(www)'       => array('www.example.com をご覧ください'),
    'BBCode'            => array('[url=http://spam]click[/url]'),
    'aタグ'             => array('<a href="#">click</a>'),
    'キリル文字'        => array('Привет, я хочу купить'),
    'アラビア文字'      => array('مرحبا بك'),
    'タイ文字'          => array('สวัสดีครับ'),
);
foreach ($spam_inputs as $label => $v) {
    t('弾く: ' . $label, eaf_spam_hit($v), true);
}

/* ===== 3. リンク遮断をオフにできる ===== */
set_opts(array('spam_block_link' => '0'));
t('オフならリンクを通す', eaf_spam_hit(array('http://example.com')), false);
t('オフでもキリル文字は弾く', eaf_spam_hit(array('Привет')), true);

/* ===== 4. NGワード ===== */
set_opts(array('spam_block_link' => '1', 'spam_words' => "SEO対策\nビットコイン\n"));
t('NGワードを弾く',           eaf_spam_hit(array('SEO対策のご提案です')), true);
t('大文字小文字を問わない',   eaf_spam_hit(array('seo対策はいかがですか')), true);
t('別のNGワードも弾く',       eaf_spam_hit(array('ビットコインで儲かります')), true);
t('関係ない文は通す',         eaf_spam_hit(array('相続した実家を売りたいです')), false);
set_opts(array('spam_block_link' => '1', 'spam_words' => ''));
t('NGワード未設定なら影響なし', eaf_spam_hit(array('SEO対策のご提案です')), false);

/* ===== 5. お名前の日本語チェック（既定オフ） ===== */
set_opts(array());
t('既定オフ: ローマ字名を通す', eaf_name_not_japanese('Taro Yamada'), false);
set_opts(array('spam_require_ja' => '1'));
t('オン: ローマ字名を弾く',     eaf_name_not_japanese('Taro Yamada'), true);
t('オン: 漢字名は通す',         eaf_name_not_japanese('山田 太郎'), false);
t('オン: ひらがな名は通す',     eaf_name_not_japanese('やまだ たろう'), false);
t('オン: カタカナ名は通す',     eaf_name_not_japanese('ヤマダ タロウ'), false);
t('オン: 半角カナも通す',       eaf_name_not_japanese('ﾔﾏﾀﾞ ﾀﾛｳ'), false);
t('オン: 空欄は弾かない',       eaf_name_not_japanese(''), false);

/* ===== 6. 自己診断：検査が動いているか ===== */
set_opts(array('spam_block_link' => '1'));
t('自己診断: 空配列は通る',     eaf_spam_hit(array()), false);
t('自己診断: 空文字だけも通る', eaf_spam_hit(array('', '   ')), false);

echo $ng ? "\n### 失敗 {$ng} 件\n" : "\n### すべて成功\n";
@unlink($GLOBALS['FAKE_STATE_FILE']);
exit($ng ? 1 : 0);
