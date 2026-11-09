# GET /candidates_on_event?service=standup

## 概要

指定したイベントに属する候補日と、それぞれの候補日に参加表明しているコメディアンIDを取得する。

## リクエスト

- メソッド：`GET`
- パラメータ：

| パラメータ | 型 | 必須 | 内容 |
|---|---|---|---|
| `event_id` | string / integer | ○ | イベントID |

### リクエスト例

```text
GET /candidates_on_event?service=standup&event_id=1
```

## 認証・利用条件

- ログイン認証は不要。
- イベントの作成者やユーザーのロールによるアクセス制限は行わない。

## 取得内容

指定された `event_id` に属する候補日を取得し、候補日ごとに参加表明しているコメディアンIDをまとめる。

### 候補日の取得

`standup_event_candidates` から、指定されたイベントに属する候補日を取得する。

- 候補日IDをキーとして使用する。
- 候補日に参加者がいない場合も空配列として保持する。

### 参加コメディアンの取得条件

以下のすべてを満たすコメディアンのみ取得する。

- `standup_comedians.status` が `active`
- `users.status` が `active`
- `user_service_roles` の `service_key` が `standup`
- `user_service_roles` の `role` が `comedian`
- `user_service_roles.status` が `active`

※ `member` ロールの有効状態は、このAPIでは個別に確認しない。

参加コメディアンIDは文字列として返す。

### 取得順序

参加表明レコードのID（`standup_event_candidate_comedians.id`）の昇順。

## レスポンス

成功時：

```json
{
  "success": true,
  "event_id": "1",
  "candidates": {
    "10": ["5", "8"],
    "11": [],
    "12": ["3"]
  }
}
```

| フィールド | 内容 |
|---|---|
| `success` | 処理結果 |
| `event_id` | 指定されたイベントID（文字列） |
| `candidates` | 候補日IDをキー、参加コメディアンIDの配列を値とするオブジェクト |

## エラー

| HTTPステータス | 内容 |
|---|---|
| `400` | イベントIDが不正 |
| `405` | GET以外のメソッド |
| `500` | 内部エラー |

## 備考

- 指定したイベントが存在しない場合も、エラーにはならず、`candidates` は空オブジェクトとして返る。
- 候補日に参加表明しているコメディアンがいない場合は、候補日IDに対応する空配列を返す。
- 無効なコメディアン、無効なユーザー、無効な `comedian` ロールの参加表明は取得結果に含めない。
- イベント自体のステータスや候補日の日時による絞り込みは行わない。