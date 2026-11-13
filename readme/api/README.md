# Standup Spot API

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

| Endpoint | Method | 概略 | 仕様書 |
|---|---|---|---|
| `/auth/google` | GET / POST | Google OAuth認証開始 | [authentication.md](./authentication.md) |
| `/auth/google/callback` | GET / POST | Google OAuthコールバック | [authentication.md](./authentication.md) |
| `/me` | GET / POST | ログインユーザー情報取得 | [authentication.md](./authentication.md) |
| `/logout` | GET / POST | ログアウト | [authentication.md](./authentication.md) |

### Standup

| Endpoint | Method | 概略 | 仕様書 |
|---|---|---|---|
| `/init` | GET | 初期表示情報取得 | [api-init.md](./api-init.md) |
| `/role` | POST | ロール管理 | [api-role.md](./api-role.md) |
| `/comedian` | GET / POST | コメディアン情報取得・更新 | [api-comedian.md](./api-comedian.md) |
| `/comedian_thumbnail` | POST | コメディアンサムネイル更新 | [api-comedian-thumbnail.md](./api-comedian-thumbnail.md) |
| `/comedian_voices` | GET / POST | コメディアンの一言取得・投稿 | [api-comedian-voices.md](./api-comedian-voices.md) |
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

| Endpoint | Method | 概略 | 仕様書 |
|---|---|---|---|
| `/stations` | GET | 周辺駅情報取得 | [api-stations.md](./api-stations.md) |
| `/feedback` | POST | フィードバック送信 | [api-feedback.md](./api-feedback.md) |

## Common Specifications

### 初期取得とイベント再取得

`/init` は画面の初期表示に必要なログイン情報、コメディアン、会場、イベント、候補日、一言などをまとめて返す。`/events` はイベント・候補日の情報を再取得するためのAPIで、イベントの作成・更新後など、初期表示データ全体の再取得が不要な場面で利用する。両APIにイベント関連の取得内容があるのは用途の違いによるものであり、どちらも各APIの取得条件に従う。


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

### Date and Time Handling

APIで扱う日時の形式およびタイムゾーンの解釈は、各エンドポイントの実装と個別仕様書に従う。

- 現行APIの日時文字列を、タイムゾーン付きISO 8601形式に一律変更することはしない。
- `YYYY-MM-DD HH:MM:SS` 形式など、タイムゾーンを明示しない日時文字列は、文字列だけからUTCまたは日本時間と断定できない。
- 日時の保存・取得・比較では、PHPとMySQLのタイムゾーン設定および対象カラムの型に注意する。
- イベントの開場日時・開始日時は `DATETIME` カラムで管理する。作成日時・更新日時などの型は各テーブルの定義に従う。

日時形式やタイムゾーンの取り扱いを変更する場合は、APIの入出力および利用側への影響を確認したうえで、実装と仕様書を合わせて更新する。

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

## 仕様書ポリシー

API仕様書は現在のPHP実装を正として作成する。

- 実装と既存仕様書に差異がある場合は、実装に合わせて仕様書を更新する。
- 実装上の問題は、仕様書に反映して正当化せず、修正候補として別途管理する。
- 不明な仕様は推測せず、確認してから記載する。
- APIの実装を変更した場合は、対応する仕様書も更新する。