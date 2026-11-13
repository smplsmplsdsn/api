# Standup DATABASE

Standup Spotサービスで使用するデータを管理する。

## Table List

| Table | Description |
|---|---|
| `standup_comedians` | コメディアン情報 |
| `standup_comedian_voices` | コメディアンの一言 |
| `standup_venues` | 会場情報 |
| `standup_venue_managers` | 会場管理者情報 |
| `standup_events` | イベント情報 |
| `standup_event_candidates` | イベントの候補日・会場情報 |
| `standup_event_candidate_comedians` | 候補日への参加表明 |

---

## standup_comedians

コメディアンのプロフィール情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | コメディアンID |
| `user_id` | BIGINT UNSIGNED | NO | - | `users.id` |
| `name` | VARCHAR(100) | NO | `''` | 表示名 |
| `thumbnail` | VARCHAR(500) | NO | `''` | サムネイル画像パス |
| `thumbnail_temp` | VARCHAR(500) | NO | `''` | 一時サムネイル画像パス |
| `instagram` | VARCHAR(255) | NO | `''` | Instagram |
| `tiktok` | VARCHAR(255) | NO | `''` | TikTok |
| `youtube` | VARCHAR(255) | NO | `''` | YouTube |
| `x` | VARCHAR(255) | NO | `''` | X |
| `status` | VARCHAR(20) | NO | `active` | コメディアン状態 |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`user_id`（`uk_standup_comedians_user_id`）
- FOREIGN KEY：`user_id` → `users.id`
- ON DELETE：CASCADE

### Status

| Value | Description |
|---|---|
| `active` | 有効 |
| `inactive` | 無効 |

1ユーザーにつき、コメディアン情報は1レコードのみ登録できる。

コメディアンとしての利用には、コメディアン情報の状態だけでなく、Commonのロール管理も関係する。

`user_service_roles.status` は本人のプロフィール編集などの権限を管理し、`standup_comedians.status` はプロフィールの有効状態を管理する。プロフィール更新時、コメディアン名と正式なプロフィール画像の両方が設定されていれば `active`、いずれかが未設定なら `inactive` とする。ロールが有効でもプロフィールが未完成なら `inactive` のままである。


---

## standup_comedian_voices

コメディアンが投稿する声を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | レコードID |
| `comedian_id` | BIGINT UNSIGNED | NO | - | `standup_comedians.id` |
| `text` | TEXT | NO | - | 投稿内容 |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 投稿日時 |

### Constraints

- PRIMARY KEY：`id`
- FOREIGN KEY：`comedian_id` → `standup_comedians.id`
- ON DELETE：CASCADE
- INDEX：`(comedian_id, id DESC)`（`idx_standup_comedian_voices_comedian_id`）

コメディアン情報が削除された場合、関連する声も連鎖削除される。

---

## standup_venues

会場情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 会場ID |
| `name` | VARCHAR(255) | NO | `''` | 会場名 |
| `address` | VARCHAR(500) | NO | `''` | 住所 |
| `latitude` | DECIMAL(10,7) | YES | NULL | 緯度 |
| `longitude` | DECIMAL(10,7) | YES | NULL | 経度 |
| `stations` | JSON | NO | JSON_ARRAY() | 最寄り駅情報 |
| `instagram` | VARCHAR(255) | NO | `''` | Instagram |
| `tiktok` | VARCHAR(255) | NO | `''` | TikTok |
| `youtube` | VARCHAR(255) | NO | `''` | YouTube |
| `x` | VARCHAR(255) | NO | `''` | X |
| `message` | TEXT | NO | `''` | 会場からのメッセージ |
| `message_for_comedian` | TEXT | NO | `''` | コメディアン向けメッセージ |
| `status` | VARCHAR(20) | NO | `active` | 会場状態 |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`

このテーブルには外部キー制約は定義されていない。

### Status

| Value | Description |
|---|---|
| `active` | 有効 |
| `inactive` | 無効 |

### Stations

`stations` はJSON形式で最寄り駅情報を保持する。

駅情報は `m_station` などの駅マスタを利用するが、DB上の外部キー制約は設定されていない。

---

## standup_venue_managers

会場と管理者ユーザーの関連を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | レコードID |
| `venue_id` | BIGINT UNSIGNED | NO | - | `standup_venues.id` |
| `user_id` | BIGINT UNSIGNED | NO | - | `users.id` |
| `status` | VARCHAR(20) | NO | `active` | 管理者状態 |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`(venue_id, user_id)`（`uk_standup_venue_managers_venue_user`）
- INDEX：`user_id`（`idx_standup_venue_managers_user_id`）
- FOREIGN KEY：`venue_id` → `standup_venues.id`
- FOREIGN KEY：`user_id` → `users.id`
- 両方の外部キーのON DELETE：CASCADE

### Status

| Value | Description |
|---|---|
| `active` | 有効 |
| `inactive` | 無効 |

### Manager Authorization

会場管理者として有効と判定するには、以下の4条件をすべて満たす必要がある。

1. `standup_venue_managers.status = active`
2. `users.status = active`
3. `user_service_roles` の `member` ロールが `active`
4. `user_service_roles` の `venue_manager` ロールが `active`

管理者情報の状態だけでは、管理者としての有効性は確定しない。

同じ会場とユーザーの組み合わせは1レコードのみ登録できる。無効化後に再登録する場合は、既存レコードを有効化する。

---

## standup_events

イベント本体の情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | イベントID |
| `creator_public_id` | CHAR(26) | NO | - | 作成者の `users.public_id` |
| `type` | VARCHAR(20) | NO | `''` | イベント種別 |
| `name` | VARCHAR(255) | NO | `''` | イベント名 |
| `message` | TEXT | NO | `''` | イベントメッセージ |
| `message_for_comedian` | TEXT | NO | `''` | コメディアン向けメッセージ |
| `image` | VARCHAR(500) | NO | `''` | イベント画像パス |
| `status` | VARCHAR(20) | NO | `recruiting` | イベント状態 |
| `confirmed_candidate_id` | BIGINT UNSIGNED | YES | NULL | 確定した候補ID |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- FOREIGN KEY：`creator_public_id` → `users.public_id`
- ON DELETE：RESTRICT
- FOREIGN KEY：`confirmed_candidate_id` → `standup_event_candidates.id`
- ON DELETE：SET NULL

### Type

| Value | Description |
|---|---|
| `openmic` | オープンマイク |
| `live` | ライブ |

### Status

| Value | Description |
|---|---|
| `recruiting` | 募集中 |
| `limited` | 特定のコメディアンを対象に募集 |
| `closed` | 募集終了 |

イベントの `status` は表示上の区分として扱い、`limited` を含め、候補日への参加表明・キャンセルをAPI側で制限する条件にはしない。


### Confirmed Candidate

`confirmed_candidate_id` は、確定した候補日を示す。

候補日が削除された場合、外部キー制約によって `NULL` に設定される。

作成者のユーザー情報が削除される場合、イベントが存在すると外部キー制約によって削除が制限される。

---

## standup_event_candidates

イベントに紐付く候補日・会場・料金情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 候補ID |
| `event_id` | BIGINT UNSIGNED | NO | - | `standup_events.id` |
| `venue_id` | BIGINT UNSIGNED | YES | NULL | `standup_venues.id` |
| `open_at` | DATETIME | YES | NULL | 開場日時 |
| `start_at` | DATETIME | YES | NULL | 開始日時 |
| `fee` | TEXT | NO | `''` | 料金情報 |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- FOREIGN KEY：`event_id` → `standup_events.id`
- `event_id` のON DELETE：CASCADE
- FOREIGN KEY：`venue_id` → `standup_venues.id`
- `venue_id` のON DELETE：RESTRICT

### Venue

`venue_id` はNULLを許可する。

会場が未設定の候補日を登録できる。

会場が候補日に紐付いている場合、その会場の削除は外部キー制約によって制限される。

会場削除APIの扱い：未来または日時未定の候補日で使用されている場合は削除不可。過去の候補日に使用履歴がある場合は `standup_venues.status` を `inactive` にする論理削除。候補日での使用履歴がない場合は物理削除する。外部キー `RESTRICT` は候補日から参照中の会場の物理削除を防ぐ。具体的な日時判定とレスポンスは `../api/api-venue-delete.md` を参照。


### Date and Time

`open_at` と `start_at` はDATETIME型で管理する。

どちらもNULLを許可する。

両方が `NULL` の日時未定候補も登録可能。取得時の対象範囲はAPIごとの取得条件に従う。画面上の表示位置はフロントエンドが決定するため、DB側では特定の表示順を保証しない。


---

## standup_event_candidate_comedians

候補日に対するコメディアンの参加表明を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | レコードID |
| `candidate_id` | BIGINT UNSIGNED | NO | - | `standup_event_candidates.id` |
| `comedian_id` | BIGINT UNSIGNED | NO | - | `standup_comedians.id` |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | 参加表明日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`(candidate_id, comedian_id)`（`uk_standup_event_candidate_comedians`）
- FOREIGN KEY：`candidate_id` → `standup_event_candidates.id`
- FOREIGN KEY：`comedian_id` → `standup_comedians.id`
- 両方の外部キーのON DELETE：CASCADE

同じ候補日に同じコメディアンが複数回参加表明することはできない。

候補日またはコメディアン情報が削除された場合、関連する参加表明も連鎖削除される。

参加キャンセル時はAPIが参加表明レコードを直接 `DELETE` する。これとは別に、参照先の候補日・コメディアンが物理削除されると `ON DELETE CASCADE` により関連する参加表明レコードが自動的に物理削除される。


---

## Table Relationships

```text
users
  │
  ├── standup_comedians
  │      │
  │      ├── standup_comedian_voices
  │      │
  │      └──────────────────────────────┐
  │                                     │
  └── standup_events                    │
         │                              │
         │                              │
         └── standup_event_candidates   │
                │       │               │
                │       └── standup_venues
                │
                └── standup_event_candidate_comedians
                       │
                       └── standup_comedians

standup_venues
  │
  └── standup_venue_managers
         │
         └── users
```

## Deletion Behavior

| Deleted Table | Related Table | Behavior |
|---|---|---|
| `users` | `standup_comedians` | CASCADE |
| `users` | `standup_venue_managers` | CASCADE |
| `users` | `standup_events` | RESTRICT |
| `standup_comedians` | `standup_comedian_voices` | CASCADE |
| `standup_comedians` | `standup_event_candidate_comedians` | CASCADE |
| `standup_venues` | `standup_venue_managers` | CASCADE |
| `standup_venues` | `standup_event_candidates` | RESTRICT |
| `standup_events` | `standup_event_candidates` | CASCADE |
| `standup_event_candidates` | `standup_event_candidate_comedians` | CASCADE |
| `standup_event_candidates` | `standup_events.confirmed_candidate_id` | SET NULL |

削除時の動作はSQLの外部キー制約に基づく。

## Engine and Charset

各テーブルのストレージエンジン・文字コードはSQL定義に従う。

- ストレージエンジン：InnoDB
- 文字コード：utf8mb4
- 照合順序：utf8mb4_unicode_ci（SQLで指定されているテーブル）