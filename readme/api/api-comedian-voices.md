# `/comedian_voices?service=standup` コメディアンの一言API

## 役割

コメディアンの一言を取得・登録・削除する。

- GET：指定したコメディアンの一言を取得
- POST：ログイン中の本人の一言を登録・削除

## リクエスト

- **Endpoint:** `/comedian_voices?service=standup`
- **Method:** GET / POST

## GET：一言取得

このGETは指定コメディアンの投稿履歴を取得する。`/init` の `latest_voices` は有効なユーザー・`member`/`comedian` ロール・プロフィールに基づく初期表示用の公開一覧であり、取得対象の判定が異なる。POSTの登録・削除はログイン中の本人に限定され、GETとは別の権限判定を行う。


### リクエスト

```http
GET /comedian_voices?service=standup&comedian_id=123
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `comedian_id` | integer | 必須 | 取得対象のコメディアンID |
| `after_id` | integer | 任意 | 指定した一言IDより新しい一言を取得 |
| `before_id` | integer | 任意 | 指定した一言IDより古い一言を取得 |

`after_id` と `before_id` は同時に指定できない。

### 認証・取得条件

GET はログイン不要。

取得対象のコメディアンについて、以下の条件を確認する。

- `comedian_id` が1以上の整数であること
- `standup_comedians.status = active` であること

ユーザーのログイン状態やロールは確認しない。

### 取得方式

#### 初回取得

`after_id` と `before_id` を指定しない場合、最新の一言から最大20件を取得する。

```http
GET /comedian_voices?service=standup&comedian_id=123
```

#### 過去の一言取得

`before_id` を指定すると、そのIDより小さい一言を最大20件取得する。

```http
GET /comedian_voices?service=standup&comedian_id=123&before_id=100
```

#### 新しい一言の取得

`after_id` を指定すると、そのIDより大きい一言をすべて取得する。

画面表示後に追加された投稿の差分取得を想定するため、現状では `after_id` の取得件数に上限を設けない。


```http
GET /comedian_voices?service=standup&comedian_id=123&after_id=100
```

### 取得順序

すべての取得方式で、一言IDの降順で返す。

### レスポンス

```json
{
  "success": true,
  "voices": [
    {
      "id": 120,
      "comedian_id": 123,
      "text": "一言の本文",
      "created_at": "2026-11-11 10:00:00"
    }
  ],
  "has_more": true
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `voices` | array | 一言一覧 |
| `has_more` | boolean | さらに過去の一言が存在するか |

`voices` の各要素：

| Key | Type | Description |
|---|---|---|
| `id` | integer | 一言ID |
| `comedian_id` | integer | コメディアンID |
| `text` | string | 一言の本文 |
| `created_at` | string | 登録日時 |

`has_more` は初回取得・過去の一言取得で、21件目が存在する場合に `true` となる。

新しい一言の取得（`after_id` 指定時）は全件取得するため、`has_more` は常に `false`。

## POST：一言登録

### リクエスト

- **Content-Type:** `application/json`

```json
{
  "text": "登録する一言"
}
```

| Key | Type | Required | Description |
|---|---|---|---|
| `text` | string | 必須 | 一言の本文 |

### 認証・権限

以下の条件をすべて満たす必要がある。

1. ログインしていること
2. `users.status = active` であること
3. Standup の有効な `member` ロールを持っていること
4. Standup の有効な `comedian` ロールを持っていること
5. 有効なコメディアンプロフィールが存在すること

登録先のコメディアンIDはログイン中のユーザーから取得する。リクエストにコメディアンIDを指定する必要はない。

### 入力制限

- 前後の空白を `trim()` で除去する。
- 空文字は登録できない。
- 最大300文字。
- 文字数は `mb_strlen()` で判定する。

### レスポンス

HTTP 200

```json
{
  "success": true,
  "voice_id": 120,
  "comedian_id": "123",
  "created_at": "2026-11-11 10:00:00"
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `voice_id` | integer | 登録した一言ID |
| `comedian_id` | string | 登録先のコメディアンID |
| `created_at` | string | 登録日時 |

## POST：一言削除

### リクエスト

```json
{
  "voice_id": 120
}
```

| Key | Type | Required | Description |
|---|---|---|---|
| `voice_id` | integer | 必須 | 削除対象の一言ID |

`voice_id` と `text` は同時に指定できない。

### 削除条件

ログイン中のユーザーの有効なコメディアンIDと、一言に登録されている `comedian_id` が一致する場合のみ削除できる。

他のコメディアンの一言は削除できない。

対象の一言が存在しない場合と、他のコメディアンの一言を指定した場合は、同じエラーを返す。

### レスポンス

HTTP 200

```json
{
  "success": true,
  "voice_id": 120
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `voice_id` | integer | 削除した一言ID |

## エラー

エラー時は以下の形式で返す。

```json
{
  "success": false,
  "error": "エラーメッセージ"
}
```

### GETのエラー

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 400 | `Invalid comedian ID` | コメディアンIDが不正、または有効なコメディアンではない |
| 400 | `Invalid after ID` | `after_id` が1以上の整数ではない |
| 400 | `Invalid before ID` | `before_id` が1以上の整数ではない |
| 400 | `Invalid cursor parameters` | `after_id` と `before_id` を同時指定した |

### POSTのエラー

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 400 | `Invalid request body` | JSONが不正、または登録・削除パラメータの指定が不正 |
| 400 | `Invalid voice ID` | `voice_id` が1以上の整数ではない |
| 400 | `Text is required` | 本文が文字列ではない、または空文字 |
| 400 | `Text is too long` | 本文が300文字を超えている |
| 401 | `Not logged in` | セッションにユーザーIDが存在しない |
| 401 | `Invalid user` | ユーザーが存在しない、または `users.status` が `active` ではない |
| 403 | `Comedian permission required` | 有効なコメディアン権限がない |
| 404 | `Voice deletion failed` | 削除対象が存在しない、または本人の一言ではない |

### 共通エラー

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 405 | `Method not allowed` | GET・POST以外のメソッド |
| 500 | `Internal server error` | 内部エラー |

内部エラーの詳細はレスポンスに含めず、サーバーログに記録する。

## 補足

- GET は公開情報の取得であり、ログインやロールの確認を行わない。
- POST は本人の有効なコメディアンプロフィールに対してのみ操作できる。
- 一言の登録・削除は `standup_comedian_voices` を対象に行う。
- 一言の削除は、ログイン中のユーザー本人のコメディアンIDとの一致を確認して行う。
- 初回取得・過去取得は最大20件だが、新しい一言の取得は件数制限を設けていない。