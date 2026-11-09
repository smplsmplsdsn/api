

# 会場情報取得・登録・更新API

## エンドポイント

`/venue?service=standup`

会場情報の取得、新規登録、更新を行う。

- `GET`：会場情報を取得
- `POST`：会場情報を新規登録・更新

## 認証・権限

ログインが必要。以下の条件を満たすユーザーが利用できる。

- `users.status` が `active`
- `standup` サービスの `member` ロールが `active`
- 次のいずれかを満たすこと
  - 有効なコメディアンである
  - `venue_manager` ロールが `active`

上記を満たさない場合はエラーを返す。

---

## GET：会場情報取得

### リクエスト

`GET /venue?service=standup&id={venue_id}`

| パラメータ | 必須 | 説明 |
|---|---|---|
| `id` | 必須 | 取得する会場ID |

会場IDは1以上の整数を文字列で指定する。

### 取得条件

- 指定した会場が存在すること。
- 会場の `status` が `active` であること。

### レスポンス

```json
{
  "success": true,
  "id": "113",
  "venue": {
    "id": "113",
    "name": "会場名",
    "address": "住所",
    "stations": [],
    "latlng": {
      "lat": 35.000000,
      "lng": 139.000000
    },
    "socialmedia": {
      "instagram": "",
      "tiktok": "",
      "youtube": "",
      "x": ""
    },
    "message": "",
    "message_for_comedian": "",
    "status": "active",
    "managers": []
  }
}
```

| 項目 | 説明 |
|---|---|
| `id` | 会場ID |
| `name` | 会場名 |
| `address` | 住所 |
| `stations` | 最寄駅情報の配列 |
| `latlng.lat` | 緯度。未設定の場合は `null` |
| `latlng.lng` | 経度。未設定の場合は `null` |
| `socialmedia` | SNS情報 |
| `message` | 会場向けメッセージ |
| `message_for_comedian` | コメディアン向けメッセージ |
| `status` | 会場のステータス |
| `managers` | 有効な担当管理者の公開ID一覧 |

`managers` には、担当管理者の登録状態、ユーザー状態、ロール状態が有効なユーザーのみ含まれる。

### エラー時

| HTTPステータス | エラー | 説明 |
|---|---|---|
| 400 | `Invalid venue id` | 会場IDが不正 |
| 401 | `Unauthorized` | 未ログイン、またはユーザーが無効 |
| 403 | `Forbidden 1` | 利用権限がない |
| 404 | `Venue not found` | 会場が存在しない、または有効ではない |
| 405 | `Method not allowed` | GET・POST以外のリクエスト |
| 500 | `Internal server error` | サーバー内部エラー |

---

## POST：会場情報の新規登録・更新

### リクエスト

`POST /venue?service=standup`

`multipart/form-data` またはフォームデータで送信する。

### パラメータ

| パラメータ | 必須 | 説明 |
|---|---|---|
| `id` | 新規登録時は省略または空文字 | 更新対象の会場ID |
| `name` | 必須 | 会場名 |
| `address` | 任意 | 住所 |
| `latitude` | 任意 | 緯度 |
| `longitude` | 任意 | 経度 |
| `stations` | 任意 | 最寄駅情報のJSON文字列 |
| `instagram` | 任意 | Instagram URL等 |
| `tiktok` | 任意 | TikTok URL等 |
| `youtube` | 任意 | YouTube URL等 |
| `x` | 任意 | X URL等 |
| `message` | 権限に応じて保存 | 会場向けメッセージ |
| `message_for_comedian` | 権限に応じて保存 | コメディアン向けメッセージ |

### 新規登録

`id` を省略するか、空文字で送信すると新規登録として扱う。

- 有効なコメディアンは、コメディアン用の保存可能項目を登録できる。
- 有効な会場管理者は、すべての項目を登録できる。
- 会場管理者が新規登録した場合、登録者をその会場の担当管理者として自動登録する。

### 更新

`id` に更新対象の会場IDを指定する。

- 更新対象の会場が存在し、`status` が `active` であること。
- 有効な担当管理者がいる場合は、その担当管理者のみ更新できる。
- 有効な担当管理者がいない場合は、有効なコメディアンまたは会場管理者が更新できる。

### 保存可能項目

| 項目 | コメディアン | 担当会場管理者 |
|---|---|---|
| `name` | ○ | ○ |
| `latitude` | ○ | ○ |
| `longitude` | ○ | ○ |
| `stations` | ○ | ○ |
| `instagram` | ○ | ○ |
| `tiktok` | ○ | ○ |
| `youtube` | ○ | ○ |
| `x` | ○ | ○ |
| `address` | × | ○ |
| `message` | × | ○ |
| `message_for_comedian` | × | ○ |

※ 有効な担当管理者がいない会場を会場管理者が更新する場合も、担当管理者として登録されていない限り、保存可能項目はコメディアンと同じ範囲となる。

※ リクエストに含まれていない項目は更新しない。権限のない項目は保存対象から除外される。

### 入力チェック

- 会場名は必須。前後の空白を除去し、255文字以内。
- 緯度は -90〜90、経度は -180〜180 の数値。
- 緯度・経度に空文字を指定すると `NULL` として保存する。
- `stations` は配列形式のJSON文字列であること。
- `stations` 内の文字列からHTMLタグを除去する。
- `address` は500文字以内。
- SNS項目はそれぞれ255文字以内。

### レスポンス

```json
{
  "success": true,
  "id": "113",
  "venue": {
    "id": "113",
    "name": "会場名",
    "address": "住所",
    "stations": [],
    "latlng": {
      "lat": 35.000000,
      "lng": 139.000000
    },
    "socialmedia": {
      "instagram": "",
      "tiktok": "",
      "youtube": "",
      "x": ""
    },
    "message": "",
    "message_for_comedian": "",
    "status": "active",
    "managers": []
  }
}
```

保存後の会場情報を取得して返す。レスポンスの項目構成は GET と同じ。

### エラー時

| HTTPステータス | エラー | 説明 |
|---|---|---|
| 400 | `Invalid venue id` | 会場IDが不正 |
| 400 | `店名・会場名は入力必須です。` | 会場名が未入力 |
| 400 | `Venue name is too long` | 会場名が255文字を超えている |
| 400 | `Invalid latitude` / `Invalid longitude` | 緯度・経度が不正 |
| 400 | `Invalid stations JSON` | 最寄駅情報のJSONが不正 |
| 400 | `Invalid {field}` | 対象項目の形式が不正 |
| 400 | `{field} is too long` | 対象項目の文字数超過 |
| 401 | `Unauthorized` | 未ログイン、またはユーザーが無効 |
| 403 | `Forbidden 1` | 利用権限がない |
| 403 | `You do not have permission to edit this venue.` | 会場の編集権限がない |
| 404 | `Venue not found` | 更新対象の会場が存在しない、または有効ではない |
| 405 | `Method not allowed` | GET・POST以外のリクエスト |
| 500 | `Internal server error` | サーバー内部エラー |

