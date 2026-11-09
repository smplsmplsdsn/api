# `/comedian?service=standup` コメディアンプロフィールAPI

## 役割

ログイン中のユーザー本人のコメディアンプロフィールを取得・更新する。

プロフィール情報には、名前・サムネイル画像・SNS情報が含まれる。

## リクエスト

- **Endpoint:** `/comedian?service=standup`
- **Method:** GET / POST

## 認証・権限

以下の条件をすべて満たす必要がある。

1. ログインしていること
2. `users.status = active` であること
3. Standup の `member` ロールが `active` であること
4. Standup の `comedian` ロールが `active` であること

対象ユーザーはセッションから取得するため、リクエストにユーザーIDを指定する必要はない。

プロフィールが `inactive` の場合でも、`comedian` ロールが有効であれば取得・更新できる。

## GET：プロフィール取得

### リクエスト

```http
GET /comedian?service=standup
```

### レスポンス

```json
{
  "success": true,
  "comedian": {
    "id": "123",
    "name": "コメディアン名",
    "thumbnail": "/public_id/standup/profile/image.jpg",
    "socialmedia": {
      "instagram": "",
      "tiktok": "",
      "youtube": "",
      "x": ""
    },
    "status": "active"
  }
}
```

| Key | Type | Description |
|---|---|---|
| `id` | string | コメディアンID |
| `name` | string | コメディアン名 |
| `thumbnail` | string | サムネイル画像のパス |
| `socialmedia` | object | SNS情報 |
| `status` | string | プロフィールの状態 |

`socialmedia` の内容：

- `instagram`
- `tiktok`
- `youtube`
- `x`

プロフィールが `inactive` の場合も取得できる。

### 参加表明済み候補日の取得

アカウントを有効化した直後など、参加表明済みの候補日情報が必要な場合は、次のパラメータを指定する。

```http
GET /comedian?service=standup&is_candidate_info=true
```

通常のプロフィール情報に加えて、`registered_candidates` が追加される。

```json
{
  "success": true,
  "comedian": {
    "id": "123",
    "name": "コメディアン名",
    "thumbnail": "/public_id/standup/profile/image.jpg",
    "socialmedia": {
      "instagram": "",
      "tiktok": "",
      "youtube": "",
      "x": ""
    },
    "status": "active",
    "registered_candidates": ["10", "20"]
  }
}
```

| Key | Type | Description |
|---|---|---|
| `registered_candidates` | array | 参加表明済みの候補日ID一覧 |

`is_candidate_info` が文字列 `true` の場合のみ取得する。

候補日IDは文字列として返す。

## POST：プロフィール更新

### リクエスト

`application/x-www-form-urlencoded` または `multipart/form-data` 形式で送信する。

| Key | Type | Description |
|---|---|---|
| `name` | string | コメディアン名 |
| `instagram` | string | Instagramアカウント情報 |
| `tiktok` | string | TikTokアカウント情報 |
| `youtube` | string | YouTubeアカウント情報 |
| `x` | string | Xアカウント情報 |

各値の前後の空白は `trim()` で除去される。

### サムネイル画像の反映

サムネイル画像そのものはこのAPIでアップロードしない。

サムネイルアップロードAPIで仮保存された画像がある場合、プロフィール更新時に仮保存画像を確認し、条件を満たしていれば正式なサムネイルとして反映する。

反映条件：

1. 仮保存画像のパスが本人のプロフィール画像フォルダ内であること。
2. ファイル名が空でなく、ファイル名部分だけで構成されていること。
3. 対象ファイルが実際に存在すること。

画像を正式反映した場合、`thumbnail_temp` を空にする。

また、確定した画像以外の同フォルダ内のファイルを削除する。

### プロフィール状態の決定

更新時に、次の条件でプロフィール状態を決定する。

| 条件 | `status` |
|---|---|
| `name` と `thumbnail` の両方が空でない | `active` |
| `name` または `thumbnail` が空 | `inactive` |

### レスポンス

```json
{
  "success": true,
  "comedian": {
    "id": "123",
    "name": "コメディアン名",
    "thumbnail": "/public_id/standup/profile/image.jpg",
    "socialmedia": {
      "instagram": "",
      "tiktok": "",
      "youtube": "",
      "x": ""
    },
    "status": "active"
  }
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `comedian` | object | 更新後のプロフィール情報 |

## エラー

エラー時は以下の形式で返す。

```json
{
  "success": false,
  "error": "エラーメッセージ"
}
```

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 401 | `Not logged in` | セッションにユーザーIDが存在しない |
| 401 | `Invalid user` | ユーザーが存在しない、または `users.status` が `active` ではない |
| 403 | `Comedian role is inactive` | Standup の `comedian` ロールが有効ではない |
| 404 | `Comedian profile not found` | POST時にプロフィールが存在しない |
| 404 | `Comedian profile not found(2)` | GET時にプロフィールが存在しない |
| 405 | `Method not allowed` | GET・POST以外のメソッド |
| 500 | `Internal server error` | 内部エラー |

内部エラーの詳細はレスポンスに含めず、サーバーログに記録する。

## 補足

- プロフィール情報はログイン中の本人のものだけを取得・更新できる。
- `comedian` ロールが有効であれば、プロフィールが `inactive` でも更新できる。
- プロフィールの有効・無効は、更新時の名前とサムネイルの状態によって決まる。
- 参加表明済み候補日の取得は、通常のプロフィール取得とは別のオプションとして提供する。