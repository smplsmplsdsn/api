# `/event?service=standup` イベント作成・編集API

## 役割

イベントの新規作成・編集を行う。

イベント本体と候補日をまとめて登録・更新する。

- 新規作成：イベントと候補日を登録する
- 編集：イベント情報と候補日を同期する

## リクエスト

- **Method:** POST
- **Endpoint:** `/event?service=standup`
- **Content-Type:** `application/x-www-form-urlencoded` または `multipart/form-data`

### リクエストパラメータ

| Key | Type | Required | Description |
|---|---|---|---|
| `id` | string | 任意 | イベントID。未指定または空文字の場合は新規作成 |
| `type` | string | 必須 | イベント種別 |
| `status` | string | 必須 | イベントステータス |
| `name` | string | 必須 | イベント名。空文字可 |
| `message` | string | 必須 | イベントメッセージ |
| `message_for_comedian` | string | 必須 | コメディアン向けメッセージ |
| `image` | string | 必須 | イベント画像のパス |
| `candidates` | string | 必須 | 候補日のJSON文字列 |

各文字列項目はHTMLタグを除去してから処理する。

### `type` イベント種別

| 値 | Description |
|---|---|
| `openmic` | オープンマイク |
| `live` | ライブ |

### `status` イベントステータス

| 値 | Description |
|---|---|
| `recruiting` | 募集中 |
| `limited` | 限定募集 |
| `closed` | 募集終了 |

### `candidates` 候補日

候補日はJSON配列として送信する。

```json
[
  {
    "id": "10",
    "venue_id": "5",
    "open_at": "2026-12-01 18:00:00",
    "start_at": "2026-12-01 19:00:00",
    "fee": "1000円"
  },
  {
    "id": "",
    "venue_id": "",
    "open_at": null,
    "start_at": null,
    "fee": ""
  }
]
```

| Key | Type | Required | Description |
|---|---|---|---|
| `id` | string \| null | 任意 | 候補日ID。空文字・未指定の場合は新規登録 |
| `venue_id` | string \| null | 任意 | 会場ID。空文字・未指定の場合は `NULL` |
| `open_at` | string \| null | 任意 | 開場日時 |
| `start_at` | string \| null | 任意 | 開演日時 |
| `fee` | string | 任意 | 料金 |

日時の形式は `Y-m-d H:i:s` とする。

日時が空文字または `null` の場合は `NULL` として登録する。

`candidates` に空配列 `[]` を指定することもできる。

## 認証・権限

以下の条件を満たす必要がある。

1. ログインしていること
2. `users.status = active` であること

### 新規作成

以下のいずれかを満たす必要がある。

- 有効なコメディアンであること
- 有効な `venue_manager` ロールを持っていること

いずれも Standup の有効な `member` ロールが必要となる。

### 編集

イベントの作成者本人のみ編集できる。

イベント作成時に使用したロールが現在も有効である必要はないが、ログイン中のユーザーとイベントの `creator_public_id` が一致している必要がある。

## 処理内容

### 新規作成

1. リクエスト内容を検証する。
2. 新規作成権限を確認する。
3. `standup_events` にイベントを登録する。
4. `candidates` に含まれる候補日を `standup_event_candidates` に登録する。
5. 候補数に応じて確定候補を設定する。
6. 保存後のイベント情報を取得する。
7. トランザクションを確定する。

### 編集

1. リクエスト内容を検証する。
2. 編集対象のイベントをロックして取得する。
3. 作成者本人であることを確認する。
4. 既存候補日をロックして取得する。
5. イベント情報を更新する。
6. 既存候補日を更新し、新規候補日を追加する。
7. リクエストに含まれない既存候補日を削除する。
8. 候補数に応じて確定候補を設定する。
9. 保存後のイベント情報を取得する。
10. トランザクションを確定する。

### 候補日の同期ルール

`candidates` は既存候補日の差分更新ではなく、送信された配列を正として同期する。

| リクエストの状態 | 処理 |
|---|---|
| `id` が空文字・未指定 | 新規候補として登録 |
| 既存の候補日ID | 該当候補日を更新 |
| 既存候補日IDが含まれない | 該当候補日を削除 |
| 空配列 `[]` | 既存候補日をすべて削除 |

既存候補日を指定する場合、その候補日が編集対象のイベントに属している必要がある。

他のイベントに属する候補日IDは指定できない。

候補日に参加表明しているコメディアンが存在する場合でも、会場・開場日時・開演日時・料金を変更できる。

### 会場IDの確認

`venue_id` が指定されている場合、`standup_venues` に該当する会場が存在することを確認する。

会場の `status` は確認しない。

### 確定候補の自動設定

候補日の登録・更新・削除後、イベントに紐づく候補日の数に応じて `confirmed_candidate_id` を設定する。

| 候補日数 | `confirmed_candidate_id` |
|---|---|
| 0件 | `NULL` |
| 1件 | 唯一の候補日ID |
| 2件以上 | `NULL` |

編集時は既存の確定候補を一旦 `NULL` にしてから、候補日の同期後に再設定する。

そのため、候補日が2件以上ある場合は、以前の確定候補が維持されない。

## レスポンス

### 成功

HTTP 200

```json
{
  "success": true,
  "id": "65",
  "event": {
    "id": "65",
    "creator_public_id": "ユーザー公開ID",
    "type": "live",
    "name": "イベント名",
    "message": "イベントメッセージ",
    "message_for_comedian": "コメディアン向けメッセージ",
    "image": "",
    "status": "recruiting",
    "confirmed_candidate_id": "10",
    "created_at": "2026-11-11 10:00:00",
    "updated_at": "2026-11-11 10:00:00",
    "candidates": [
      {
        "id": "10",
        "event_id": "65",
        "venue_id": "5",
        "open_at": "2026-12-01 18:00:00",
        "start_at": "2026-12-01 19:00:00",
        "fee": "1000円"
      }
    ]
  }
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `id` | string | 保存したイベントID |
| `event` | object | 保存後のイベント情報 |

`event` の各項目：

| Key | Type | Description |
|---|---|---|
| `id` | string | イベントID |
| `creator_public_id` | string | 作成者のユーザー公開ID |
| `type` | string | イベント種別 |
| `name` | string | イベント名 |
| `message` | string | イベントメッセージ |
| `message_for_comedian` | string | コメディアン向けメッセージ |
| `image` | string | イベント画像のパス |
| `status` | string | イベントステータス |
| `confirmed_candidate_id` | string \| null | 確定候補日ID |
| `created_at` | string | 作成日時 |
| `updated_at` | string | 更新日時 |
| `candidates` | array | 候補日一覧 |

候補日の各項目：

| Key | Type | Description |
|---|---|---|
| `id` | string | 候補日ID |
| `event_id` | string | イベントID |
| `venue_id` | string \| null | 会場ID |
| `open_at` | string \| null | 開場日時 |
| `start_at` | string \| null | 開演日時 |
| `fee` | string | 料金 |

イベントID・候補日ID・会場IDは文字列として返す。

候補日はIDの昇順で返す。

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
| 400 | `Invalid event id` | イベントIDが不正 |
| 400 | `Invalid event type` | 許可されていないイベント種別 |
| 400 | `Invalid event status` | 許可されていないイベントステータス |
| 400 | `Invalid name` | イベント名が文字列ではない |
| 400 | `Event name is too long` | イベント名が255文字を超えている |
| 400 | `Invalid message` | メッセージが文字列ではない |
| 400 | `Invalid message_for_comedian` | コメディアン向けメッセージが文字列ではない |
| 400 | `Invalid image` | 画像パスが文字列ではない |
| 400 | `Event image path is too long` | 画像パスが500文字を超えている |
| 400 | `Candidates are required` | `candidates` が未指定、または文字列ではない |
| 400 | `Invalid candidates JSON` | 候補日のJSONが不正、または配列ではない |
| 400 | `Invalid candidate data` | 候補日の要素がオブジェクトではない |
| 400 | `Invalid candidate id` | 候補日IDが不正、または対象イベントに属していない |
| 400 | `Duplicate candidate id` | 同じ候補日IDが重複している |
| 400 | `Invalid venue id` | 会場IDが不正 |
| 400 | `Invalid open_at` | 開場日時の形式が不正 |
| 400 | `Invalid start_at` | 開演日時の形式が不正 |
| 400 | `Invalid fee` | 料金が文字列ではない |
| 400 | `Venue not found` | 指定された会場が存在しない |
| 401 | `Unauthorized` | 未ログイン、またはユーザーが有効ではない |
| 403 | `Forbidden` | 新規作成権限がない |
| 403 | `You do not have permission to edit this event.` | イベント作成者本人ではない |
| 404 | `Event not found` | 編集対象のイベントが存在しない |
| 405 | `Method not allowed` | POST以外のメソッド |
| 500 | `Failed to retrieve event` | 保存後のイベント情報を取得できない |
| 500 | `Internal server error` | その他の内部エラー |

内部エラーの詳細はレスポンスに含めず、サーバーログに記録する。

## トランザクション

イベント本体と候補日の登録・更新・削除は、1つのトランザクション内で実行する。

処理中にエラーが発生した場合はロールバックし、イベント本体と候補日の変更を取り消す。

## 補足

- イベント作成時の `creator_public_id` はログインユーザーの公開IDを使用する。
- イベント編集は作成者本人のみ可能。
- イベントのステータスにかかわらず編集できる。
- 候補日の参加表明状況にかかわらず、候補日の各項目を編集できる。
- 候補日の削除時は、関連する参加表明データも外部キー制約に従って削除される。
- このAPIでは画像ファイル自体のアップロードは行わず、画像パスを保存する。