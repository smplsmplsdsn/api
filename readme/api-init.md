# Standup：初期表示APIの仕様書


## /init?service=standup の役割

Standupサイトの初期表示に必要な情報を、1回のAPIリクエストでまとめてJSONとして返す。

対象ファイル：
src/services/standup/init.php

レスポンス構成：

```json
{
  "login": {},
  "comedians": [],
  "venues": [],
  "events": [],
  "past_events": [],
  "latest_voices": []
}
```

## 1. login

Standupサービスにおける現在のログイン状態と、ログインユーザーの基本情報を返す。

ログイン状態は、セッションの

`$_SESSION['services']['standup']['user_id']`

をもとに判定する。

#### レスポンス

ログイン中：

```json
{
  "logged_in": true,
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
  "user_public_id": "",
  "nickname": "",
  "profiles": []
}
```

#### 各項目

* `logged_in`

  * Standupのログイン状態。
  * セッションに保存されたユーザーが存在し、`users.status = "active"` の場合 `true`。
  * それ以外は `false`。

* `user_public_id`

  * ログインユーザーの `users.public_id`。
  * 外部からユーザーを識別するために使用する。
  * ログアウト中は `""`。

* `nickname`

  * ログインユーザーの `users.nickname`。
  * ログアウト中は `""`。

* `profiles`

  * `user_service_roles` から、`service_key = "standup"` かつ `status = "active"` のロールを取得する。
  * 複数のロールを持つ場合は配列で返す。
  * ログアウト中は `[]`。

#### Standup comedian の判定

Standupにおける comedian としての有効状態は、以下の両方を満たす場合とする。

1. `profiles` に `"comedian"` が含まれる
2. `standup_comedians` に該当ユーザーのレコードが存在し、`status = "active"` である

この判定結果は `login` レスポンスには直接含めない。

ただし、この判定は以下の情報の表示条件として使用する。

* `events[].message_for_comedian`
* `venues[].message_for_comedian`
* 日時未定のイベント候補の表示

なお、`users.id` は内部識別子として使用するが、APIレスポンスでは公開しない。


## 2. comedians

`standup_comedians` に登録されているコメディアン情報を返す。

`standup_comedians.status = "active"` のコメディアンを対象とする。

#### レスポンス

```json
{
  "id": "1",
  "name": "テストコメディアン",
  "thumbnail": "",
  "status": "active",
  "is_me": true,
  "registered_candidates": [
    "7",
    "8"
  ],
  "socialmedia": {
    "instagram": "",
    "tiktok": "",
    "youtube": "",
    "x": ""
  }
}
```

#### 各項目

* `id`

  * `standup_comedians.id`
  * APIでは文字列として返す。
  * `standup_comedians.id` は外部に公開して問題ないIDとして扱う。
  * `users.id` は使用しない。

* `name`

  * `standup_comedians.name`

* `thumbnail`

  * `standup_comedians.thumbnail`

* `status`

  * `standup_comedians.status`
  * `active` のコメディアンのみ返す。

* `is_me`

  * 現在ログインしているユーザー自身のコメディアンプロフィールかどうかを表す。
  * ログインユーザーの `users.id` と、`standup_comedians.user_id` が一致する場合 `true`。
  * それ以外は `false`。
  * 未ログインの場合はすべて `false`。

* `registered_candidates`

  * そのコメディアンが出演登録されている `standup_event_candidates.id` の一覧。
  * APIでは文字列として返す。
  * イベントが `events` に含まれる候補だけでなく、`past_events` に含まれる候補も対象とする。
  * 出演登録がない場合は `[]`。
  * 同じ候補IDは重複して返さない。

* `socialmedia`

  * `standup_comedians` に登録されているSNS情報。
  * 以下のキーを固定で返す。

```json
{
  "instagram": "",
  "tiktok": "",
  "youtube": "",
  "x": ""
}
```

#### SNS情報

各値は `standup_comedians` の対応するカラムをそのまま返す。

未設定の場合は現在のDB定義により `""` を返す。

#### コメディアンIDについて

`standup_comedians.id` はAPIで公開可能なIDとして扱う。

一方、`standup_comedians.user_id` は `users.id` を参照する内部IDのため、APIレスポンスには出さない。




## 3. venues

`standup_venues` に登録されている会場情報を返す。

イベントで現在使用されているかどうかに関係なく、`standup_venues` に登録されている会場をすべて対象とする。

#### レスポンス

```json
{
  "id": 1,
  "name": "テスト会場A",
  "address": "東京都渋谷区テスト1-1-1",
  "latlng": {
    "lat": 35.658034,
    "lng": 139.701636
  },
  "stations": [
    {
      "line": "JR山手線",
      "name": "渋谷駅",
      "distance": 5
    }
  ],
  "socialmedia": {
    "instagram": "https://instagram.com/test_a",
    "tiktok": "",
    "youtube": "",
    "x": "https://x.com/test_a"
  },
  "message": "一般向けテストメッセージ",
  "message_for_comedian": "コメディアン向けテストメッセージ",
  "status": "active"
}
```

#### 各項目

* `id`

  * `standup_venues.id`
  * 会場を識別するID。
  * APIでは整数として返す。

* `name`

  * `standup_venues.name`

* `address`

  * `standup_venues.address`

* `latlng`

  * 会場の緯度・経度。
  * `latitude` / `longitude` をAPIでは `lat` / `lng` として返す。
  * 緯度または経度が未設定の場合は `null`。

```json
{
  "lat": 35.658034,
  "lng": 139.701636
}
```

* `stations`

  * `standup_venues.stations` に保存されているJSONを配列として返す。
  * 未設定の場合は `[]`。

* `socialmedia`

  * 会場に登録されているSNS情報。
  * 以下のキーを固定で返す。

```json
{
  "instagram": "",
  "tiktok": "",
  "youtube": "",
  "x": ""
}
```

* 各値は `standup_venues` の対応するカラムをそのまま返す。

* 未設定の場合は `""`。

* `message`

  * 一般ユーザー向けの会場メッセージ。
  * `standup_venues.message` を返す。

* `message_for_comedian`

  * comedian向けの会場メッセージ。
  * ログインユーザーが有効なStandup comedianの場合は `standup_venues.message_for_comedian` を返す。
  * それ以外の場合は `""`。

* `status`

  * `standup_venues.status`

#### comedian向け情報の表示条件

`message_for_comedian` を表示するには、ログインユーザーが以下の両方を満たす必要がある。

1. `user_service_roles` に `service_key = "standup"`、`role = "comedian"`、`status = "active"` のロールを持つ
2. `standup_comedians` に該当ユーザーの `status = "active"` のレコードが存在する

条件を満たさない場合は、`message_for_comedian` を `""` とする。

#### 会場の取得条件

`standup_venues` に登録されているすべての会場を取得する。

イベント候補との関連有無は、会場の取得条件には影響しない。


## 4. events

`standup_events`、`standup_event_candidates`、`standup_event_candidate_comedians` を使用して、Standupのイベント情報を返す。

1つのイベントに対して複数の日時場所を候補として登録できる。

候補ごとに開催日時を判定し、現在開催中・未来の候補を `events`、過去の候補を `past_events` に分けて返す。

#### イベントのレスポンス

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

#### イベント各項目

* `id`

  * `standup_events.id`

* `creator_public_id`

  * イベント作成者の `users.public_id`。
  * `standup_events.creator_public_id` を返す。
  * `users.id` はAPIに出さない。

* `type`

  * `standup_events.type`

* `name`

  * `standup_events.name`

* `message`

  * `standup_events.message`

* `message_for_comedian`

  * `standup_events.message_for_comedian`
  * ログインユーザーが有効なStandup comedianの場合のみ返す。
  * それ以外の場合は `""`。

* `image`

  * `standup_events.image`

* `status`

  * `standup_events.status`
  * イベントの状態を表す。
  * `/init` APIでは `status` の値による表示・非表示の判定は行わない。
  * DBに保存されている値をそのまま返す。
  * `status` の具体的な値や用途はイベント管理側の仕様で定義する。

* `confirmed_candidate_id`

  * `standup_events.confirmed_candidate_id`
  * 確定している候補がある場合はその `candidate.id`。
  * 未確定の場合は `null`。

* `candidates`

  * イベントに登録されている候補一覧。
  * 候補ごとに表示条件を判定する。
  * 表示対象となる候補が複数ある場合は、すべて配列で返す。

#### 候補のレスポンス

```json
{
  "id": 7,
  "venue_id": "1",
  "open_at": "2026-10-18 18:00:00",
  "start_at": "2026-10-18 19:00:00",
  "fee": "1000円",
  "comedian_ids": [
    "1"
  ]
}
```

#### 候補各項目

* `id`

  * `standup_event_candidates.id`
  * 整数として返す。

* `venue_id`

  * `standup_event_candidates.venue_id`
  * APIでは文字列として返す。
  * 会場が未設定の場合は `null`。

* `open_at`

  * `standup_event_candidates.open_at`
  * 未設定の場合は `null`。

* `start_at`

  * `standup_event_candidates.start_at`
  * 未設定の場合は `null`。

* `fee`

  * `standup_event_candidates.fee`
  * 未設定の場合は `""`。

* `comedian_ids`

  * `standup_event_candidate_comedians.comedian_id` の一覧。
  * `standup_comedians.id` を使用する。
  * `users.public_id` は使用しない。
  * APIでは文字列として返す。
  * 出演登録がない場合は `[]`。

#### events と past_events

レスポンスには以下の2つの配列を持つ。

```json
{
  "events": [],
  "past_events": []
}
```

`events` は現在開催中または未来の候補、`past_events` は過去の候補を含むイベント。

候補単位で判定するため、1つのイベントに過去と未来の候補が混在している場合、同じイベントが `events` と `past_events` の両方に含まれることがある。

各配列では、候補が1件も残らないイベントは返さない。

#### 候補の日時判定

候補の判定日時は以下のルールで決定する。

1. `start_at` が設定されている場合は `start_at`
2. `start_at` が未設定で `open_at` が設定されている場合は `open_at`
3. 両方が未設定の場合は「日時未定」

判定日時が現在日時以前の場合は `past_events` に含める。

判定日時が現在日時より後の場合は `events` に含める。

#### 日時未定の候補

`start_at` と `open_at` の両方が未設定の候補は、以下の両方を満たす場合のみ `events` に表示する。

1. ログインユーザーがイベントの作成者本人
2. ログインユーザーが有効なStandup comedian

それ以外の場合は表示しない。

#### comedian向け情報

`message_for_comedian` を表示する条件は、ログイン情報におけるStandup comedian判定と同じ。

以下の両方を満たす場合に `message_for_comedian` を返す。

1. `user_service_roles` に `service_key = "standup"`、`role = "comedian"`、`status = "active"` のロールを持つ
2. `standup_comedians` に該当ユーザーの `status = "active"` のレコードが存在する

条件を満たさない場合は `""` を返す。

#### 出演コメディアン

候補に出演登録されているコメディアンを `comedian_ids` として返す。

出演登録の取得時には、

* `standup_comedians.status = "active"`
* 紐付く `users.status = "active"`

の両方を満たすコメディアンを対象とする。

`comedian_ids` は `standup_comedians.id` を文字列として返す。

出演登録がない場合は `[]`。

#### イベントの並び順

イベントは `standup_events.id` の降順で取得する。

同一イベント内の候補は `standup_event_candidates.id` の昇順で処理する。



## 5. latest_voices

Standupの有効な comedian による最新一言を取得する。

**最新の一言を持つ有効な comedian のうち、最新の10人を返す。**

1人の comedian につき1件だけ表示する。

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

- `voice_id`
  - `standup_comedian_voices.id`
  - 整数として返す。

- `comedian_id`
  - `standup_comedian_voices.comedian_id`
  - `standup_comedians.id` を使用する。
  - APIでは文字列として返す。

- `is_me`
  - 現在ログインしているユーザー自身の一言かどうかを表す。
  - `standup_comedians.user_id` と、ログインユーザーの内部IDである `users.id` が一致する場合は `true`。
  - それ以外は `false`。
  - 未ログインの場合は `false`。
  - APIレスポンスには `users.id` を含めない。

- `name`
  - `standup_comedians.name`
  - `users.nickname` は使用しない。

- `thumbnail`
  - `standup_comedians.thumbnail`

- `text`
  - `standup_comedian_voices.text`

- `created_at`
  - `standup_comedian_voices.created_at`

### 対象となる comedian

以下の条件をすべて満たす comedian を対象とする。

1. `users.status = "active"` である。
2. `user_service_roles` に `service_key = "standup"`、`role = "comedian"`、`status = "active"` のロールを持つ。
3. `user_service_roles` に `service_key = "standup"`、`role = "member"`、`status = "active"` のロールを持つ。
4. `standup_comedians.status = "active"` である。
5. `standup_comedian_voices` に一言を1件以上持つ。

対象となる一言は、`standup_comedian_voices.comedian_id` から `standup_comedians` を特定し、そのプロフィールに紐づくものとする。

### 1人につき最新1件

各 comedian について、`standup_comedian_voices` から最新の一言を1件だけ取得する。

最新の判定は以下の順とする。

1. `created_at` の新しい順
2. `created_at` が同じ場合は `id` の大きい順

### 表示件数

各 comedian の最新一言を取得した後、最新一言の新しい順に最大10人を返す。

同じ comedian が複数の一言を投稿していても、`latest_voices` には最大1件しか登場しない。

### 並び順

`latest_voices` は以下の順で返す。

1. `created_at` の新しい順
2. `created_at` が同じ場合は `voice_id`（`standup_comedian_voices.id`）の大きい順

### 本人判定

`is_me` は、ログインユーザーの `users.id` と `standup_comedians.user_id` を比較して判定する。

- 本人の一言：`true`
- 他の comedian の一言：`false`
- 未ログイン時：`false`

`users.id` は内部識別子として使用し、APIレスポンスには公開しない。

### comedian 情報

`name` と `thumbnail` は `users` の情報ではなく、`standup_comedians` の情報を使用する。

投稿者の公開IDである `user_public_id` はレスポンスに含めない。

