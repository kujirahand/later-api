# later-api

later-cli のデータ同期用 API / Web サービスです。

- Webサービス: `/index.php`
- API: `/api.php`

## 要件に対応した保存先

- ユーザー情報: `/data/users.db`
- ユーザーデータ: `/data/users/user-<bucket>.db`
  - 10人ごとに 1 つの SQLite DB を利用します（例: `user-0.db`, `user-1.db`）

## API 仕様（最小実装）

### 1. メールアドレス認証 + APIキー発行

`POST /api.php?action=register`

Body (form or JSON):

- `email`

Response:

- `user_id`
- `email`
- `api_key`
- `bucket`

### 2. データ同期（取得）

`GET /api.php?action=sync&api_key=<API_KEY>`

Response:

- `data`
- `updated_at`

### 3. データ同期（保存）

`POST /api.php?action=sync`

Body (form or JSON):

- `api_key`
- `data` (JSON object/array)

