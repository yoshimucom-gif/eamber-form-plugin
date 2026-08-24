# e.Amber お問い合わせフォーム（eamber-form）の通しテスト環境

本番サイトで試すとメールが実送信され、リードに実データが入るため**厳禁**。ここで完結させる。

対象プラグイン: `../eamber-form/eamber-form.php`（`__DIR__` 起点の相対参照）

## 使い方

```bash
C:/Users/yoshi/php-portable/php82/php.exe -S 127.0.0.1:4492 router.php
```

- `/` フォーム（`?design=compact|card`）
- `/twice` 同一ページに2つ設置（id重複の確認） / `/lp?n=6` ティザーをLP風に複数設置
- `/settings` 設定画面 / `/leads` 申込一覧 / `/csv` CSV出力
- `/delete?id=N` 申込削除 / `/testmail` テストメール
- `/__seed` 運営者情報などを投入（`?third_party=1` で第三者提供ON）
- `/__setopt?k=..&v=..` オプション1件設定（項目モードの切替）
- `/__state` 保存リード・送信メール・オプションをJSONで確認
- `/__reset` 状態リセット / `/__oldtable` 旧バージョンのテーブル定義に差し替え
- `?noactivate=1` 有効化フックを飛ばす（自動更新だけが走った状況の再現）

単体テスト:

```bash
C:/Users/yoshi/php-portable/php82/php.exe unit.php          # 設定保存・メール末尾・正規化など
C:/Users/yoshi/php-portable/php82/php.exe color_test.php    # 色設定の保存とフォームへの反映
C:/Users/yoshi/php-portable/php82/php.exe glue_test.php     # ショートコード属性のスペース抜けの救済
C:/Users/yoshi/php-portable/php82/php.exe parse_test.php    # WP本体と同じ属性パーサで上を検証
C:/Users/yoshi/php-portable/php82/php.exe recipes_test.php  # 設定画面に載せているコピペ用の例が実際に動くか
C:/Users/yoshi/php-portable/php82/php.exe disc_test.php     # 免責が出るべき場所に出て、ティザーには出ないこと
C:/Users/yoshi/php-portable/php82/php.exe logo_test.php     # アイコン画像の保存・出力と、危険なURLの遮断
C:/Users/yoshi/php-portable/php82/php.exe multi_test.php    # 1ページに複数置いたときのid一意性とCSS/JSの重複防止
C:/Users/yoshi/php-portable/php82/php.exe page_test.php     # お問い合わせページ(form)の自動作成とティザー遷移先の既定値
C:/Users/yoshi/php-portable/php82/php.exe address_test.php  # 市町村セレクト（27市町村＋県外）
C:/Users/yoshi/php-portable/php82/php.exe thirdparty_test.php # 第三者提供の表示と提供先リンク
C:/Users/yoshi/php-portable/php82/php.exe contact_test.php  # フォーム上の問い合わせ先の表示制御
C:/Users/yoshi/php-portable/php82/php.exe companyimg_test.php # 対応会社の画像（丸トリミング）
C:/Users/yoshi/php-portable/php82/php.exe spam_test.php     # スパム判定（誤爆しないことを厚めに検査）
C:/Users/yoshi/php-portable/php82/php.exe company_test.php  # 対応会社の設定タブとメール末尾の連絡先
C:/Users/yoshi/php-portable/php82/php.exe security_test.php # ★権限・CSRF・XSS・CSV注入を実際に攻撃して確かめる
C:/Users/yoshi/php-portable/php82/php.exe updater_test.php  # GitHubのupdate.jsonを実際に取得して更新配信を検証
```

`-n` を付けて起動すると php.ini を無視する＝**mbstring 無しのサーバー**を再現できる。

## ハマりどころ

- **日本語を含む絶対パスをPHPに直書きしない**。Windowsのコードページ差で解決できないことがある
  → `__DIR__` / `dirname(__DIR__)` 起点で書く（このフォルダは日本語パスの下にある）
- 日本語を含む送信テストは **Git Bash の curl だと cp932 で壊れる**
  → Python の urllib（UTF-8指定）かブラウザの fetch を使う
- `computer screenshot` はこの環境でタイムアウトする → `javascript_tool` で実測ジオメトリを見る
- Python 側には Windows パス（`C:\...`）を渡す。`/tmp/...` は届かない
- **テストスクリプトは Write ツールで作る**。`php -r '...'` に日本語を直接書くとcp932で化け、
  一致しないのに「無い」と出て**逆の結論**になる。日本語を含む検査キーは
  `strlen($KEY) === 12` のような健全性チェックを先に置く
  （キーが空になると `strpos` が 0 を返し、全部「出ている」と誤判定する）
- `php -S` は同時接続でデッドロックする＝並列テスト不可（逐次で）
- FakeWpdb は VARCHAR を**文字数**で判定する（MySQLと同じ。バイト数で数えると日本語で誤判定する）
- **スタブを手抜きすると検査が逆方向に嘘をつく**。`esc_url_raw` を trim だけにしていたため
  `javascript:` が素通りし「危険なURLが保存される」と誤報した（本番のWPは落とす）。
  スタブは本物の挙動に合わせること
- ポータブルPHPにはCAバンドルが無いため、実HTTPを使うテストは `CURLSSLOPT_NATIVE_CA` を指定している

## 対象プラグインの切り替え

`router.php` 冒頭の `require_once` のパスと、`eaf_` 系の関数名（設定画面・一覧の呼び出し）を
対象プラグインに合わせて書き換える。現在は `eamber-form`（e.Amber お問い合わせフォーム）を指している。

## PHP

`C:\Users\yoshi\php-portable\php82\`（8.2.33 NTS）。`php.ini` で mbstring / openssl / curl を有効化済み。


## security_test.php について

申込テーブルには お名前・電話・メール・市町村 が入る。ここが漏れるのが
このプラグインで最も重い事故なので、「権限チェックを書いた」ではなく
**書いたものが実際に止めるか**を、未ログイン・購読者・CSRF の3通りで試している。

検査を成立させるために `wp_stub.php` は次を切り替えられる:

- `$GLOBALS['FAKE_CAPS']`         … 権限。`array()` で未ログイン、`array('read')` で購読者
                                     （未設定なら管理者。既存テストとの互換のため）
- `$GLOBALS['FAKE_STRICT_NONCE']` … 立てると check_admin_referer が本物同様に検査する
- `$GLOBALS['FAKE_WP_DIE_THROW']` … wp_die を例外にして、止まったことを捕まえられる

CSV出力や nonce 失敗は最後に `exit` するため、1シナリオずつ `sec_case.php` を
子プロセスで起動して出力を受け取っている。

**この検査が空振りしていないことの確認方法**: `eaf_leads_page()` 冒頭の
`current_user_can` の行を消すと「★申込一覧を直接呼んでも漏れない」が NG になる。
