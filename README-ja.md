# Later API (日本語)

`Later API` は、タスク管理 CLI ツール [later-cli](https://github.com/kujirahand/later-cli) のデータを安全かつ効率的に同期するための、軽量で高性能な PHP 製 API および Web サービス（ダッシュボード）です。

## 稼働中のWebサービスについて

- **公式ホスティング**: [aoikujira.com/later-api](https://aoikujira.com/later-api/)
  - すぐに Later API を試してみたい方は、こちらの公式ホスティングサービスをご利用ください。メールアドレスさえあれば、アカウントを作成して、Later CLI と連携してタスクの同期を開始できます。

## 特徴

- 👤 **マルチユーザー対応**
  - 複数のユーザーがそれぞれ独立してアカウントを作成し、タスクを同期できます。
- 🔑 **セキュアなパスワードレス認証 (OTP)**
  - パスワードの代わりに、登録されたメールアドレス宛に送信されるワンタイムパスコード（OTP）を使用してログインします。サーバー側にパスワードを保存しません。
- 🗄️ **SQLite シャーディング（分散データ保存）**
  - 全ユーザーの共通情報（ユーザー一覧、メタデータ、API キーなど）は `data/users.db` に保存されます。
  - 各ユーザーのタスクデータは、10ユーザーごとに分割された SQLite データベース（例: `data/users/user-0.db` など）へ自動的にシャーディングされます。これにより、大規模なデータ容量でもパフォーマンス低下を防ぎます。
- 🎫 **柔軟な API キー管理**
  - ユーザーごとに最大 10 個の API キーを発行・管理できます。
  - キーの有効期限は「1年、3年、5年、10年」から選択可能です。
  - セキュリティ保護のため、発行時の画面で一度だけキーが表示され、サーバー上にはハッシュ化された値のみが記録されます。
- 📧 **メールデバッグモード搭載**
  - ローカル開発やテストの際、本物のメールサーバーを構築しなくても動作確認ができるよう、送信メールの内容をログファイル（`data/mail_debug.log`）に出力し、画面上にもコードを表示するデバッガー機能を備えています。
- 🔌 **ゼロ依存関係 (Zero Dependency)**
  - 外部のライブラリ（Composer パッケージなど）に依存せず、PHP 8.0+ と標準の SQLite3 拡張のみで高速に動作します。

---

## システム要件

- **PHP 8.0 以上** (推奨)
  - 厳密な型宣言 (`strict_types`) やモダンな型ヒントを使用しているため、PHP 8.0 以降の環境を推奨します。
- **SQLite3 拡張** (`pdo_sqlite` が有効であること)
- **Web サーバー** (Apache / Nginx、またはローカル開発用の PHP ビルトインサーバー)
- **メール送信環境** (実環境での利用時)
  - `mail()` 関数による送信、または外部 SMTP サーバー。

---

## 詳細なインストール手順

### ステップ 1: リポジトリのクローン

ソースコードをサーバー上の公開ディレクトリにクローンまたは配置します。

```bash
git clone https://github.com/kujirahand/later-api.git
cd later-api
```

### ステップ 2: 設定ファイルの作成と編集

テンプレートファイル `config.tpl.php` をコピーして、実環境用の設定ファイル `config.inc.php` を作成します。

```bash
cp config.tpl.php config.inc.php
```

`config.inc.php` をテキストエディタで開き、環境に合わせて設定値を変更します。

#### 主な設定項目

```php
return [
    // データベース保存先の指定
    'db' => [
        'users_db' => __DIR__ . '/data/users.db',   // メインユーザーデータベース
        'users_dir' => __DIR__ . '/data/users',     // 各ユーザーのデータ保存ディレクトリ
    ],

    // メール送信の設定
    'mail' => [
        // 'log': ローカルファイル（data/mail_debug.log）に書き出す（開発・テスト用）
        // 'mail': PHP 標準の mail() 関数を使用
        // 'smtp': カスタム SMTP サーバーを使用
        'driver' => 'log', 

        // SMTPを使用する場合の設定 (driver => 'smtp' の場合のみ有効)
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls', // 'ssl', 'tls' または null
        'smtp_username' => 'your-username@example.com',
        'smtp_password' => 'your-smtp-password',

        // メールの差出人情報
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Later API',
    ],

    // セッションのセキュリティ設定
    'session' => [
        'cookie_secure' => false, // HTTPS環境では true に変更してください
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    // デバッグモードの有無
    'debug' => true, // 本番環境では false に設定してください
];
```

#### Gmailなどの外部SMTPサーバーを利用する場合

`smtp_host`、`smtp_port`、`smtp_secure`、`smtp_username`、`smtp_password` を適切に設定してください。

```php
// ...
    'mail' => [
        'driver' => 'smtp',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_username' => '***@gmail.com', // Gmailのメールアドレス
        'smtp_password' => '***', // Gmailのアプリパスワード（2段階認証を有効にして発行）
        // Sender information
        'from_address' => '***@gmail.com',
        'from_name' => 'later-api',
    ],
// ...
```

### ステップ 3: ディレクトリ権限の設定

SQLite はファイルベースのデータベースであるため、Web サーバーがデータベースファイルを新規作成・書き込みできるように、`data/` ディレクトリの所有者と権限を適切に設定します。

```bash
# 例: Webサーバー実行ユーザーが _www や www-data の場合
chmod 755 data
# もしくは書き込み権限を付与
chmod -R 777 data
```

> [!IMPORTANT]
> `data/` 配下には、自動的に SQLite のデータベースファイル（`users.db` や `users/user-*.db`）が作成されます。
> セキュリティのため、`data/` 内にはすでに `.htaccess` ファイルが設置されており、Apache などの Web サーバー経由でデータベースファイルに直接アクセスできないように保護されています。Nginx をご利用の場合は、設定ファイルで `data/` ディレクトリ配下への直接の HTTP アクセスを拒否するように設定してください。

### ステップ 4: データベースの自動初期化

特別なデータベース初期化手順は不要です。
Web サービス（`index.php`）または API（`api.php`）に初めてアクセスした際に、`users.sql` および `user_db.sql` から必要なテーブル構造が **自動的に生成されます**。

---

## 起動方法（動作確認）

### 1. 開発用の簡易起動（PHP ビルトインサーバー）

開発環境やローカルでの動作確認には、PHP に内蔵されている Web サーバー機能を利用すると最も簡単です。

```bash
php -S localhost:8000
```

起動後、ブラウザで [http://localhost:8000](http://localhost:8000) にアクセスすると、Later API の Web 管理画面（ダッシュボード）が表示されます。

- **新規登録**: メールアドレスとニックネームを入力して登録します。`driver => 'log'` の状態であれば、メール送信画面のフッター、もしくは `data/mail_debug.log` ファイル内に認証コードが出力されますので、それを入力して認証を完了させます。
- **ログイン**: 登録したメールアドレスを入力して、再度認証コードによるログインを行います。
- **APIキーの発行**: ログイン後のダッシュボードで、有効期限を選択して新しい API キーを発行します。**キーは一度しか表示されないため、必ずコピーして保存してください。**

### 2. 本番環境（Apache / Nginx）での運用

本番環境の Web サーバー（ドキュメントルート）にクローンしたディレクトリを紐付けます。

- ディレクトリ内の `index.php` がユーザー用ダッシュボード（Web画面）のエントリポイントとなります。
- `api.php` が同期用 API のエントリポイントとなります。

---

## API 仕様

API へのすべてのリクエストは、**POST メソッド**で行います。また、リクエストの HTTP ヘッダーに **Bearer トークン**として API キーを含める必要があります。

### 認証ヘッダー

```http
Authorization: Bearer <YOUR_API_KEY>
Content-Type: application/json
```

### 1. 認証テスト (hello)

API キーが有効であるかどうかをテストするためのエンドポイントです。

- **エンドポイント**: `POST /api.php?method=hello`
- **リクエストボディ (JSON)**:
  ```json
  {
      "message": "Hello, Later API!"
  }
  ```
- **レスポンス (JSON)**:
  ```json
  {
      "message": "Hello, Later API!"
  }
  ```

### 2. イベントの記録 (post)

`later-cli` で発生したタスク操作イベント（タスクの追加・削除・ステータス変更など）を送信して同期します。

- **エンドポイント**: `POST /api.php?method=post`
- **リクエストボディ (JSON)**:
  ```json
  {
      "events": [
          {
              "event": "add",
              "guid": "123e4567-e89b-12d3-a456-426614174000",
              "timestamp": "2026-05-29 14:00:00",
              "task": "タスクの内容",
              "status": "done",
              "date": "2026-05-30 08:00:00"
          },
          {
              "event": "delete",
              "guid": "987f6543-e21b-32d1-b654-216614174000",
              "timestamp": "2026-05-29 14:05:00",
              "task": "不要なタスク",
              "status": "pending",
              "date": "2026-05-30 08:05:00"
          }
      ]
  }
  ```
- **レスポンス (JSON)**:
  ```json
  {
      "success": true,
      "message": "Events processed successfully",
      "inserted": 2,
      "skipped": 0
  }
  ```

### 3. イベントの取得 (get)

指定した期間内に記録された同期イベントを取得します。

- **エンドポイント**: `POST /api.php?method=get`
- **リクエストボディ (JSON)**:
  ```json
  {
      "date_from": "2026-05-29 00:00:00",
      "date_to": "2026-05-30 23:59:59"
  }
  ```
  *(※ `date_to` を省略した場合は現在日時、`date_from` を省略した場合は `date_to` の7日前が自動的にセットされます)*
- **レスポンス (JSON)**:
  ```json
  [
      {
          "event": "add",
          "guid": "123e4567-e89b-12d3-a456-426614174000",
          "timestamp": "2026-05-29 14:00:00",
          "task": "タスクの内容",
          "status": "done",
          "date": "2026-05-30 08:00:00"
      }
  ]
  ```

---

## 統合テストの実行

本 API には、自動統合テストスイートが付属しています。
以下のコマンドを実行すると、一時的なテスト用データベースを用いて Web サーバーをバックグラウンドで自動起動し、API 疎通テストや機能検証を一気に行います。

```bash
php tests/api_test.php
```

テスト完了後、一時ファイルは自動クリーンアップされ、元の構成（`config.inc.php`）に戻されます。

---

## ライセンス

[MIT License](LICENSE)

