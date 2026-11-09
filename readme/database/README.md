# Standup Spot DATABASE Documentation

Standup Spot で使用するデータベースの構造、テーブル間の関連、制約などを管理する。

## Database Structure

データベースは用途ごとにディレクトリを分けて管理する。

| Directory | Description | Documentation |
|---|---|---|
| `000_common` | ユーザー・認証・ロール管理 | [common.md](./common.md) |
| `001_standup` | Standup Spotのサービス固有データ | [standup.md](./standup.md) |
| `101_stations` | 駅・路線情報 | [stations.md](./stations.md) |

## Table List

### Common

| Table | Description |
|---|---|
| `users` | ユーザー情報 |
| `user_remember_tokens` | ログイン継続用トークン |
| `user_auth` | 外部認証情報 |
| `user_service_roles` | サービスごとのロール管理 |

### Standup

| Table | Description |
|---|---|
| `standup_comedians` | コメディアン情報 |
| `standup_comedian_voices` | コメディアンの声 |
| `standup_venues` | 会場情報 |
| `standup_venue_managers` | 会場管理者情報 |
| `standup_events` | イベント情報 |
| `standup_event_candidates` | イベントの候補日・会場情報 |
| `standup_event_candidate_comedians` | 候補日への参加表明 |

### Stations

| Table | Description |
|---|---|
| `m_station` | 駅情報 |
| `m_line` | 路線情報 |
| `m_station_join` | 同一駅・乗換駅の関連情報 |

## ID Management

ユーザー・サービス固有データでは、用途に応じてIDを使い分ける。

### Internal ID

`users.id` はデータベース内部でユーザーを識別するためのID。

主に外部キーによるテーブル間の関連付けに使用する。

### Public ID

`users.public_id` は外部に公開するユーザー識別用ID。

イベントの作成者など、外部に公開するデータのユーザー識別に使用する。

### Service-specific ID

サービス固有のデータは、それぞれのテーブルの `id` を使用する。

例：

- `standup_comedians.id`
- `standup_venues.id`
- `standup_events.id`
- `standup_event_candidates.id`

これらは各サービス固有のデータを識別するためのIDであり、`users.id` や `users.public_id` とは区別する。

## Table Relationships

主なテーブル間の関連は以下のとおり。

### Common

- `users` を基点として認証情報・ロール・ログイン継続用トークンを管理する。
- `user_auth.user_id` は `users.id` を参照する。
- `user_service_roles.user_id` は `users.id` を参照する。
- `user_remember_tokens.user_id` は `users.id` を参照する。

### Standup

- `standup_comedians.user_id` は `users.id` を参照する。
- `standup_venue_managers.user_id` は `users.id` を参照する。
- `standup_venue_managers.venue_id` は `standup_venues.id` を参照する。
- `standup_comedian_voices.comedian_id` は `standup_comedians.id` を参照する。
- `standup_events.creator_public_id` は `users.public_id` を参照する。
- `standup_event_candidates.event_id` は `standup_events.id` を参照する。
- `standup_event_candidates.venue_id` は `standup_venues.id` を参照する。
- `standup_event_candidate_comedians.candidate_id` は `standup_event_candidates.id` を参照する。
- `standup_event_candidate_comedians.comedian_id` は `standup_comedians.id` を参照する。
- `standup_events.confirmed_candidate_id` は `standup_event_candidates.id` を参照する。

### Stations

- `m_station` は駅情報を管理する。
- `m_line` は路線情報を管理する。
- `m_station_join` は路線上の駅同士の関連を管理する。

## Foreign Keys and Deletion

テーブル間の外部キー制約と削除時の動作は、各テーブルのSQL定義に従う。

主な削除動作：

- `ON DELETE CASCADE`：参照元のデータも連鎖削除する。
- `ON DELETE SET NULL`：参照元のカラムをNULLにする。
- `ON DELETE RESTRICT`：関連データが存在する場合、削除を制限する。

各テーブルの詳細は個別のDATABASE仕様書を参照する。

## SQL Files

テーブル定義および制約は、`database/` 以下のSQLファイルで管理する。

仕様書とSQLの内容が異なる場合は、現在のSQL定義を正とする。