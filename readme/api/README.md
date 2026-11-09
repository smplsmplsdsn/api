# Standup Spot API Documentation

Standup Spot API のエンドポイント、共通仕様、認証・権限管理に関するドキュメント。

## API Overview

APIはPHPによる単一のエントリーポイント（`src/index.php`）でリクエストを受け付け、URLに応じて各サービスの処理を実行する。

### Base URL

本番環境：

```text
https://api.standupspot.com/
```

### Routing

StandupサービスのAPIは、`service=standup` を指定して利用する。

```text
/{endpoint}?service=standup
```

共通APIはサービス固有のパラメータを必要としないものと、サービスを指定するものがある。

HTTPメソッド、パラメータ、認証条件などは各API仕様書を参照する。

## API List

### Authentication

| Endpoint | Method | Description | Documentation |
|---|---|---|---|
| `/auth/google` | GET | Google OAuth認証開始 | [authentication.md](./authentication.md) |
| `/auth/google/callback` | GET | Google OAuthコールバック | [authentication.md](./authentication.md) |
| `/me` | GET | ログインユーザー情報取得 | [authentication.md](./authentication.md) |
| `/logout` | GET | ログアウト | [authentication.md](./authentication.md) |

### Standup

| Endpoint | Method | Description | Documentation |
|---|---|---|---|
| `/init` | GET | 初期表示情報取得 | [api-init.md](./api-init.md) |
| `/role` | POST | ロール管理 | [api-role.md](./api-role.md) |
| `/comedian` | GET / POST | コメディアン情報取得・更新 | [api-comedian.md](./api-comedian.md) |
| `/comedian_thumbnail` | POST | コメディアンサムネイル更新 | [api-comedian-thumbnail.md](./api-comedian-thumbnail.md) |
| `/comedian_voices` | GET / POST | コメディアンの声取得・投稿 | [api-comedian-voices.md](./api-comedian-voices.md) |
| `/event` | POST | イベント登録・編集 | [api-event.md](./api-event.md) |
| `/event_delete` | POST | イベント削除 | [api-event-delete.md](./api-event-delete.md) |
| `/events` | GET | イベント・候補情報取得 | [api-events.md](./api-events.md) |
| `/candidate_participation` | POST | 候補日への参加・キャンセル | [api-candidate-participation.md](./api-candidate-participation.md) |
| `/candidates_on_event` | GET | イベント単位の候補・参加者取得 | [api-candidates-on-event.md](./api-candidates-on-event.md) |
| `/candidates_on_comedian` | GET | コメディアン単位の候補取得 | [api-candidates-on-comedian.md](./api-candidates-on-comedian.md) |
| `/candidates_on_venue` | GET | 会場単位の候補取得 | [api-candidates-on-venue.md](./api-candidates-on-venue.md) |
| `/venue` | GET / POST | 会場情報取得・登録・更新 | [api-venue.md](./api-venue.md) |
| `/venue_delete` | POST | 会場削除 | [api-venue-delete.md](./api-venue-delete.md) |
| `/venue_manager` | POST | 会場管理者の登録・解除 | [api-venue-manager.md](./api-venue-manager.md) |

### Other APIs

| Endpoint | Method | Description | Documentation |
|---|---|---|---|
| `/stations` | GET | 周辺駅情報取得 | [api-stations.md](./api-stations.md) |
| `/feedback` | POST | フィードバック送信 | [api-feedback.md](./api-feedback.md) |

## Common Specifications

### HTTP Methods

各APIで許可するHTTPメソッドを定義する。

- `GET`：情報取得
- `POST`：情報登録・更新・削除など

許可されていないメソッドでリクエストされた場合の動作は、各APIの実装に従う。

### Request Format

リクエストパラメータの受け取り方法はAPIごとに異なる。

- GET：URLクエリパラメータ
- POST：POSTパラメータまたはJSON

具体的なパラメータ名・形式・必須条件は各API仕様書に記載する。

### Response Format

JSONを返すAPIでは、UTF-8を使用する。

エラー時のレスポンス形式は、各APIの実装に従う。

### Authentication

ログイン状態はPHPセッションで管理する。

サービスごとにログイン状態を保持し、Standupサービスでは `service=standup` を使用する。

認証方式、セッション、Remember Tokenの詳細は [authentication.md](./authentication.md) を参照する。

### Authorization

APIごとにログイン状態およびロールを確認する。

Standupサービスで使用する主なロール：

- `member`
- `comedian`
- `venue_manager`

ロールの有効条件や各APIの利用条件は、それぞれの仕様書に記載する。

## Documentation Policy

API仕様書は現在のPHP実装を正として作成する。

- 実装と既存仕様書に差異がある場合は、実装に合わせて仕様書を更新する。
- 実装上の問題は、仕様書に反映して正当化せず、修正候補として別途管理する。
- 不明な仕様は推測せず、確認してから記載する。
- APIの実装を変更した場合は、対応する仕様書も更新する。