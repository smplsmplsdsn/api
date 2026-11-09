# `/init?service=standup` 初期表示API

## 役割

Standup Spot の初期表示に必要な情報をまとめて取得するAPI。

ログイン情報、コメディアン、会場、イベント、候補日、最新の一言、アップロード上限を返す。

ログインしていない状態でも利用できる。

## リクエスト

- **Method:** GET
- **Endpoint:** `/init?service=standup`

GET以外のリクエストは受け付けない。

## レスポンス

```json
{
  "login": {
    "logged_in": true,
    "is_account_limited": false,
    "user_public_id": "ユーザー公開ID",
    "nickname": "ニックネーム",
    "profiles": ["member", "comedian"]
  },
  "comedians": [],
  "venues": [],
  "events": [],
  "event_candidates": [],
  "latest_voices": [],
  "limits": {
    "upload_max_filesize": 2097152,
    "post_max_size": 8388608
  }
}
```

### `login` ログイン情報

| Key | Type | Description |
|---|---|---|
| `logged_in` | boolean | ログイン状態 |
| `is_account_limited` | boolean | 有効な member ロールがない場合は `true` |
| `user_public_id` | string | ユーザー公開ID。未ログイン時は空文字 |
| `nickname` | string | ニックネーム。未ログイン時は空文字 |
| `profiles` | array | Standup の有効なロール一覧 |

`profiles` には `user_service_roles` のうち、`service_key = standup` かつ `status = active` のロールが格納される。

#### ログイン判定

- セッションにユーザーIDが存在すること。
- Standup の有効な `member` ロールを持っていること。
- `users.status` が `active` であること。

有効な `member` ロールがない場合、ログインユーザーとして扱わず、`is_account_limited` を `true` にする。

### `comedians` コメディアン一覧

有効なコメディアンを取得する。

取得条件：

- `standup_comedians.status = active`
- `users.status = active`
- `user_service_roles` に Standup の有効な `member` ロールが存在する
- `user_service_roles` に Standup の有効な `comedian` ロールが存在する

| Key | Type | Description |
|---|---|---|
| `id` | string | コメディアンID |
| `name` | string | コメディアン名 |
| `thumbnail` | string | サムネイル画像 |
| `status` | string | コメディアンのステータス |
| `is_me` | boolean | ログインユーザー本人かどうか |
| `socialmedia` | object | SNS情報 |

`socialmedia` の内容：

- `instagram`
- `tiktok`
- `youtube`
- `x`

IDの昇順で取得する。

### `venues` 会場一覧

有効な会場を取得する。

取得条件：

- `standup_venues.status = active`

| Key | Type | Description |
|---|---|---|
| `id` | string | 会場ID |
| `name` | string | 会場名 |
| `address` | string | 住所 |
| `latlng` | object | 緯度・経度 |
| `stations` | array | 最寄り駅情報 |
| `socialmedia` | object | SNS情報 |
| `message` | string | 会場からのメッセージ |
| `message_for_comedian` | string | コメディアン向けメッセージ |
| `managers` | array | 有効な会場管理者のユーザー公開ID |
| `status` | string | 会場のステータス |

`latlng` の内容：

- `lat`: 緯度。未設定の場合は `null`
- `lng`: 経度。未設定の場合は `null`

`socialmedia` の内容：

- `instagram`
- `tiktok`
- `youtube`
- `x`

`managers` には、次の条件をすべて満たす会場管理者を格納する。

1. `standup_venue_managers.status = active`
2. `users.status = active`
3. Standup の `member` ロールが `active`
4. Standup の `venue_manager` ロールが `active`

`message_for_comedian` は、ログインユーザーが有効なコメディアン、または当該会場の管理者である場合に取得する。それ以外の場合は空文字を返す。

会場IDの降順で取得する。

### `events` イベント一覧

候補日の有無にかかわらず、`standup_events` の全イベントを取得する。

ステータスによる絞り込みは行わない。

| Key | Type | Description |
|---|---|---|
| `id` | number | イベントID |
| `creator_public_id` | string | 作成者のユーザー公開ID |
| `type` | string | イベント種別 |
| `name` | string | イベント名 |
| `message` | string | イベントメッセージ |
| `message_for_comedian` | string | コメディアン向けメッセージ |
| `image` | string | 画像パス |
| `status` | string | イベントステータス |
| `confirmed_candidate_id` | number \| null | 確定した候補日ID |

`message_for_comedian` は、ログインユーザーが有効なコメディアン、または当該イベントの作成者である場合に取得する。それ以外の場合は空文字を返す。

イベントIDの降順で取得する。

### `event_candidates` イベント候補日一覧

イベント候補日を取得する。

| Key | Type | Description |
|---|---|---|
| `id` | string | 候補日ID |
| `event_id` | string | イベントID |
| `venue_id` | string \| null | 会場ID。未設定の場合は `null` |
| `open_at` | string \| null | 開場日時 |
| `start_at` | string \| null | 開演日時 |
| `fee` | string | 料金 |
| `comedian_ids` | array | 参加表明しているコメディアンID一覧 |

#### 候補日の取得条件

候補日の日時判定には、`start_at` を優先して使用する。`start_at` が未設定の場合は `open_at` を使用する。

- 判定に使用する日時が今日より前の場合は取得しない。
- 今日以降の候補日は取得する。
- `start_at` と `open_at` の両方が未設定の場合は、イベント作成者本人であり、かつ有効なコメディアンまたは有効な会場管理者である場合に限り取得する。

過去の判定は時刻単位ではなく日付単位で行う。そのため、今日の候補日は時刻にかかわらず取得対象となる。

#### `comedian_ids`

取得対象となった候補日に紐づく `standup_event_candidate_comedians` の `comedian_id` を格納する。

コメディアンの有効性による追加の絞り込みは行わない。

候補日は `COALESCE(start_at, open_at)` の昇順、同じ日時の場合は候補日IDの昇順で取得する。

### `latest_voices` 最新の一言

コメディアンごとの最新の一言を取得する。全体で最大10件。

取得条件：

- `standup_comedian_voices` に登録されている一言
- `standup_comedians.status = active`
- `users.status = active`
- Standup の有効な `member` ロールが存在する
- Standup の有効な `comedian` ロールが存在する

コメディアンごとに、`created_at` が最も新しい一言を取得する。同じ日時の場合は、IDが大きいものを最新とする。

| Key | Type | Description |
|---|---|---|
| `voice_id` | number | 一言ID |
| `comedian_id` | string | コメディアンID |
| `is_me` | boolean | ログインユーザー本人かどうか |
| `name` | string | コメディアン名 |
| `thumbnail` | string | サムネイル画像 |
| `text` | string | 一言の本文 |
| `created_at` | string | 登録日時 |

登録日時の降順、IDの降順で並べる。

### `limits` アップロード上限

PHPの設定値をバイト単位で返す。

| Key | Type | Description |
|---|---|---|
| `upload_max_filesize` | number | `upload_max_filesize` の値 |
| `post_max_size` | number | `post_max_size` の値 |

## 補足

- イベント情報と候補日情報は別々の配列で返す。
- 候補日が存在しないイベントも `events` に含まれる。
- ログイン状態やロールによって、一部のメッセージや候補日の取得範囲が変わる。
- コメディアン・会場・イベント・最新の一言は、それぞれ独立した取得処理で構成されている。