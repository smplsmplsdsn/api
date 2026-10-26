# Standup：初期表示API仕様書

## `/init?service=standup` の役割

Standupサイトの初期表示に必要な情報を、1回のAPIリクエストでまとめてJSONとして返す。

**対象ファイル：**

`src/services/standup/init.php`

### レスポンス構成

```json
{
  "login": {},
  "comedians": [],
  "venues": [],
  "events": [],
  "latest_voices": [],
  "limits": {}
}
```

---

## 1. login

Standupサービスにおける現在のログイン状態と、ログインユーザーの基本情報を返す。

ログイン状態は、セッションの

`$_SESSION['services']['standup']['user_id']`

をもとに判定する。

### member権限による制限

セッションにユーザーIDが存在する場合、`hasActiveMember()` によってStandupの有効なmember権限を確認する。

有効なmember権限がない場合は、以下の処理を行う。

- ログインユーザーIDを `null` にする。
- `is_account_limited` を `true` にする。
- ログアウト状態として以降の処理を行う。

### レスポンス

ログイン中：

```json
{
  "logged_in": true,
  "is_account_limited": false,
  "user_public_id": "01XXXXXXXXXXXXXXX",
  "nickname": "...",
  "profiles": [
    "member",
    "comedian"
  ]
}
```

ログアウト中：

```json
{
  "logged_in": false,
  "is_account_limited": false,
  "user_public_id": "",
  "nickname": "",
  "profiles": []
}
```

アカウント制限中：

```json
{
  "logged_in": false,
  "is_account_limited": true,
  "user_public_id": "",
  "nickname": "",
  "profiles": []
}
```

### 各項目

#### `logged_in`

- Standupのログイン状態。
- セッションに保存されたユーザーが存在し、`users.status = "active"` であり、有効なmember権限を持つ場合 `true`。
- それ以外は `false`。

#### `is_account_limited`

- Standupにおいてアカウントが制限されているかを表す。
- セッションにユーザーIDが存在するものの、`hasActiveMember()` が `false` を返した場合 `true`。
- それ以外は `false`。

#### `user_public_id`

- ログインユーザーの `users.public_id`。
- ログアウト中およびアカウント制限中は `""`。

#### `nickname`

- ログインユーザーの `users.nickname`。
- ログアウト中およびアカウント制限中は `""`。

#### `profiles`

- `user_service_roles` から、以下の条件に一致するロールを取得する。
  - `service_key = "standup"`
  - `status = "active"`
- 複数のロールを持つ場合は配列で返す。
- ログアウト中およびアカウント制限中は `[]`。

### Standup comedian の判定

Standupにおける有効なcomedianとしての状態は、以下の両方を満たす場合とする。

1. `profiles` に `"comedian"` が含まれる。
2. `standup_comedians` に該当ユーザーのレコードが存在し、`status = "active"` である。

この判定結果は `login` レスポンスには直接含めない。

### Standup venue_manager の判定

有効なvenue_managerとしての状態は、`profiles` に `"venue_manager"` が含まれる場合とする。

ただし、venue_managerの有効性は、会場ごとの管理者登録状況とは別に扱う。

### 内部IDについて

`users.id` は内部識別子として使用するが、APIレスポンスでは公開しない。

---

## 2. comedians

`standup_comedians` に登録されている有効なコメディアン情報を返す。

### 取得条件

以下のすべてを満たすコメディアンを対象とする。

1. `standup_comedians.status = "active"`
2. 紐づく `users.status = "active"`
3. `user_service_roles` に以下の有効なロールが存在する。
   - `service_key = "standup"`
   - `role = "member"`
   - `status = "active"`
4. `user_service_roles` に以下の有効なロールが存在する。
   - `service_key = "standup"`
   - `role = "comedian"`
   - `status = "active"`

取得順は `standup_comedians.id` の昇順。

### レスポンス

```json
{
  "id": "1",
  "name": "テストコメディアン",
  "thumbnail": "",
  "status": "active",
  "is_me": true,
  "socialmedia": {
    "instagram": "",
    "tiktok": "",
    "youtube": "",
    "x": ""
  }
}
```

### 各項目

#### `id`

- `standup_comedians.id`
- APIでは文字列として返す。
- 外部に公開可能なIDとして扱う。

#### `name`

- `standup_comedians.name`

#### `thumbnail`

- `standup_comedians.thumbnail`

#### `status`

- `standup_comedians.status`
- 取得対象は `active` のみ。

#### `is_me`

- 現在ログインしているユーザー自身のコメディアンプロフィールかどうかを表す。
- ログインユーザーの `users.id` と `standup_comedians.user_id` が一致する場合 `true`。
- それ以外は `false`。
- 未ログインの場合はすべて `false`。

#### `socialmedia`

- `standup_comedians` に登録されているSNS情報。
- 以下のキーを固定で返す。

```json
{
  "instagram": "",
  "tiktok": "",
  "youtube": "",
  "x": ""
}
```

各値は対応するDBカラムの値をそのまま返す。

### コメディアンIDについて

`standup_comedians.id` は公開可能なIDとして扱う。

一方、`standup_comedians.user_id` は `users.id` を参照する内部IDのため、APIレスポンスには出さない。

### registered_candidates について

現在の実装では `registered_candidates` は返さない。

---

## 3. venues

`standup_venues` に登録されている有効な会場情報を返す。

### 取得条件

- `standup_venues.status = "active"`
- イベント候補との関連有無は取得条件に影響しない。

取得順は `standup_venues.id` の降順。

### レスポンス

```json
{
  "id": "1",
  "name": "テスト会場A",
  "address": "東京都渋谷区テスト1-1-1",
  "latlng": {
    "lat": 35.658034,
    "lng": 139.701636
  },
  "stations": [],
  "socialmedia": {
    "instagram": "",
    "tiktok": "",
    "youtube": "",
    "x": ""
  },
  "message": "一般向けテストメッセージ",
  "message_for_comedian": "コメディアン向けテストメッセージ",
  "managers": [
    "01XXXXXXXXXXXXXXX"
  ],
  "status": "active"
}
```

### 各項目

#### `id`

- `standup_venues.id`
- APIでは文字列として返す。

#### `name`

- `standup_venues.name`

#### `address`

- `standup_venues.address`

#### `latlng`

- `latitude` / `longitude` を `lat` / `lng` として返す。
- 各値は数値型に変換する。
- 未設定の場合は `null`。

```json
{
  "lat": 35.658034,
  "lng": 139.701636
}
```

#### `stations`

- `standup_venues.stations` に保存されているJSONを配列として返す。
- 未設定の場合は `[]`。

#### `socialmedia`

以下のキーを固定で返す。

```json
{
  "instagram": "",
  "tiktok": "",
  "youtube": "",
  "x": ""
}
```

各値は `standup_venues` の対応するカラムをそのまま返す。

#### `message`

- 一般ユーザー向けの会場メッセージ。
- `standup_venues.message` を返す。

#### `message_for_comedian`

以下のいずれかを満たす場合に `standup_venues.message_for_comedian` を返す。

1. ログインユーザーが有効なStandup comedianである。
2. ログインユーザーが該当会場の有効なvenue_managerである。

それ以外の場合は `""`。

#### `managers`

- 該当会場に登録されている有効な管理者の `users.public_id` 一覧。
- 以下のすべてを満たす管理者を対象とする。
  - `standup_venue_managers.status = "active"`
  - `user_service_roles.service_key = "standup"`
  - `user_service_roles.role = "venue_manager"`
  - `user_service_roles.status = "active"`
  - `users.status = "active"`
- APIでは `users.public_id` を文字列配列として返す。
- 該当する管理者がいない場合は `[]`。

#### `status`

- `standup_venues.status`

### 会場管理者本人の判定

ログインユーザーの `user_public_id` が `managers` に含まれる場合、その会場の管理者本人と判定する。

この判定は `message_for_comedian` の表示条件にも使用する。

### 内部IDについて

会場管理者の判定には内部の `users.id` を使用するが、APIレスポンスの `managers` には `users.public_id` のみを返す。

---

## 4. events

`standup_events`、`standup_event_candidates`、`standup_event_candidate_comedians` を使用して、現在開催中または未来のイベント情報を返す。

現在の実装では、過去のイベント情報は取得しない。

### 取得方法

- `standup_events` を基準に取得する。
- `standup_event_candidates` を `LEFT JOIN` で取得する。
- イベントは `standup_events.id` の降順。
- 同一イベント内の候補は `standup_event_candidates.id` の昇順。

イベントの `status` による取得制限は行わない。

### イベントのレスポンス

```json
{
  "id": 5,
  "creator_public_id": "a1",
  "type": "live",
  "name": "未来の候補が複数",
  "message": "未来の候補を複数持つテストイベントです。",
  "message_for_comedian": "",
  "image": "",
  "status": "pending",
  "confirmed_candidate_id": null,
  "candidates": []
}
```

### イベント各項目

#### `id`

- `standup_events.id`
- 整数として返す。

#### `creator_public_id`

- `standup_events.creator_public_id`
- イベント作成者の `users.public_id`。
- 内部IDである `users.id` は公開しない。

#### `type`

- `standup_events.type`

#### `name`

- `standup_events.name`

#### `message`

- `standup_events.message`

#### `message_for_comedian`

以下のいずれかを満たす場合に `standup_events.message_for_comedian` を返す。

1. ログインユーザーが有効なStandup comedianである。
2. ログインユーザーがイベントの作成者本人である。

それ以外の場合は `""`。

#### `image`

- `standup_events.image`

#### `status`

- `standup_events.status`
- DBに保存されている値をそのまま返す。
- `/init` APIでは `status` による表示・非表示の判定は行わない。

#### `confirmed_candidate_id`

- `standup_events.confirmed_candidate_id`
- 値が存在する場合は整数として返す。
- 未確定の場合は `null`。

#### `candidates`

- イベントに登録されている候補一覧。
- 候補ごとに表示条件を判定する。
- 表示対象となる候補を配列として返す。
- 表示対象の候補がないイベントは返さない。

### 候補のレスポンス

```json
{
  "id": 7,
  "venue_id": "1",
  "open_at": "2026-10-18 18:00:00",
  "start_at": "2026-10-18 19:00:00",
  "fee": "1000円",
  "comedian_ids": []
}
```

### 候補各項目

#### `id`

- `standup_event_candidates.id`
- 整数として返す。

#### `venue_id`

- `standup_event_candidates.venue_id`
- APIでは文字列として返す。
- 会場未設定の場合は `null`。

#### `open_at`

- `standup_event_candidates.open_at`
- 未設定の場合は `null`。

#### `start_at`

- `standup_event_candidates.start_at`
- 未設定の場合は `null`。

#### `fee`

- `standup_event_candidates.fee`
- 未設定の場合は `""`。

#### `comedian_ids`

- `standup_event_candidate_comedians.comedian_id` の一覧。
- `standup_comedians.id` を使用する。
- APIでは文字列として返す。
- 出演登録がない場合は `[]`。
- 現在の実装では、コメディアンやユーザーの `status` による絞り込みは行わない。
- 登録テーブルの `id` の昇順で取得する。

### 候補の日時判定

候補の判定日時は以下のルールで決定する。

1. `start_at` が設定されている場合は `start_at`。
2. `start_at` が未設定で `open_at` が設定されている場合は `open_at`。
3. 両方が未設定の場合は日時未定。

判定日時が現在日時より後の場合は `events` に含める。

判定日時が現在日時以前の場合は取得対象外とする。

### 日時未定の候補

`start_at` と `open_at` の両方が未設定の候補は、以下のすべてを満たす場合のみ `events` に表示する。

1. ログインユーザーである。
2. ログインユーザーがイベントの作成者本人である。
3. ログインユーザーが有効なStandup comedian、または有効なvenue_managerである。

それ以外の場合は表示しない。

### 出演コメディアン

候補に出演登録されている `comedian_id` を取得する。

- `standup_event_candidate_comedians.comedian_id` を使用する。
- `standup_comedians.id` を文字列として返す。
- `users.id` や `users.public_id` は使用しない。
- 現在の実装では、有効・無効状態による絞り込みは行わない。

### イベントの表示条件まとめ

| 候補の日時 | 表示条件 |
|---|---|
| 未来 | 全ユーザーに表示 |
| 現在日時以前 | 表示しない |
| 日時未定 | 作成者本人かつ有効なcomedianまたはvenue_manager |

なお、1つのイベントに複数の候補が存在する場合、表示条件を満たす候補のみを返す。

候補が1件も残らないイベントは返さない。

---

## 5. latest_voices

Standupの有効なcomedianによる最新一言を取得する。

**最新の一言を持つ有効なcomedianのうち、最新の10人を返す。**

1人のcomedianにつき1件だけ表示する。

### レスポンス

```json
{
  "voice_id": 18,
  "comedian_id": "6",
  "is_me": true,
  "name": "テストコメディアン",
  "thumbnail": "",
  "text": "皆さんに楽しんでもらえるライブにします！",
  "created_at": "2026-10-21 21:47:48"
}
```

### 各項目

#### `voice_id`

- `standup_comedian_voices.id`
- 整数として返す。

#### `comedian_id`

- `standup_comedian_voices.comedian_id`
- `standup_comedians.id` を使用する。
- APIでは文字列として返す。

#### `is_me`

- ログインユーザーの `users.id` と `standup_comedians.user_id` が一致する場合 `true`。
- それ以外は `false`。
- 未ログインの場合は `false`。

#### `name`

- `standup_comedians.name`
- `users.nickname` は使用しない。

#### `thumbnail`

- `standup_comedians.thumbnail`

#### `text`

- `standup_comedian_voices.text`

#### `created_at`

- `standup_comedian_voices.created_at`

### 対象となるcomedian

以下の条件をすべて満たすcomedianを対象とする。

1. `users.status = "active"`。
2. `user_service_roles` に以下の有効なロールが存在する。
   - `service_key = "standup"`
   - `role = "comedian"`
   - `status = "active"`
3. `user_service_roles` に以下の有効なロールが存在する。
   - `service_key = "standup"`
   - `role = "member"`
   - `status = "active"`
4. `standup_comedians.status = "active"`。
5. `standup_comedian_voices` に一言を1件以上持つ。

### 1人につき最新1件

各comedianについて、`standup_comedian_voices` から最新の一言を1件だけ取得する。

最新の判定は以下の順とする。

1. `created_at` の新しい順。
2. `created_at` が同じ場合は `id` の大きい順。

### 表示件数・並び順

- 各comedianの最新一言を取得した後、最大10人を返す。
- `created_at` の新しい順。
- `created_at` が同じ場合は `voice_id` の大きい順。

同じcomedianが複数の一言を投稿していても、最大1件しか登場しない。

### 本人判定

`is_me` はログインユーザーの `users.id` と `standup_comedians.user_id` を比較して判定する。

`users.id` は内部識別子として使用し、APIレスポンスには公開しない。

---

## 6. limits

PHPのアップロード関連設定値を取得し、フロントエンドに返す。

### レスポンス

```json
{
  "upload_max_filesize": 104857600,
  "post_max_size": 104857600
}
```

### 各項目

#### `upload_max_filesize`

- PHPの `upload_max_filesize` の値。
- `getIniBytes()` を使用してバイト数に変換する。

#### `post_max_size`

- PHPの `post_max_size` の値。
- `getIniBytes()` を使用してバイト数に変換する。

いずれも整数のバイト数として返す。

---

## 7. APIレスポンス全体

最終的なレスポンス構成は以下のとおり。

```json
{
  "login": {
    "logged_in": true,
    "is_account_limited": false,
    "user_public_id": "01XXXXXXXXXXXXXXX",
    "nickname": "テストユーザー",
    "profiles": [
      "member",
      "comedian"
    ]
  },
  "comedians": [],
  "venues": [],
  "events": [],
  "latest_voices": [],
  "limits": {
    "upload_max_filesize": 104857600,
    "post_max_size": 104857600
  }
}
```

---

## 8. 実装上の補足・確認事項

現在の実装における注意点をまとめる。

| 項目 | 現在の実装 |
|---|---|
| `is_account_limited` | member権限がなければ `true` |
| `users.status` が inactive の場合 | `logged_in = false`、`is_account_limited = false` |
| イベントの `message_for_comedian` | 有効なcomedianまたはイベント作成者本人に表示 |
| 会場の `message_for_comedian` | 有効なcomedianまたは該当会場の管理者に表示 |
| 出演コメディアン | IDをそのまま取得。無効状態による絞り込みなし |
| 日時未定候補 | 作成者本人かつ有効なcomedianまたはvenue_managerに表示 |
| 過去イベント | 取得しない |
| `registered_candidates` | 返さない |
| `limits` | アップロード関連のPHP設定値を返す |

### `is_account_limited` について

現在の実装では、`is_account_limited` は厳密には「アカウント自体が無効か」ではなく、「有効なStandup member権限を持たないか」を表す。

`users.status != "active"` の場合は `logged_in = false` となるが、`is_account_limited` は `true` にはならない。