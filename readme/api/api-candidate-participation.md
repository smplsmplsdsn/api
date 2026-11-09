# POST /candidate-participation?service=standup

## 概要

コメディアンがイベント候補日に対して参加表明、または参加キャンセルを行う。

## リクエスト

- メソッド：`POST`
- Content-Type：`application/json`

### パラメータ

| パラメータ | 型 | 必須 | 内容 |
|---|---|---|---|
| `action` | string | ○ | `join` または `cancel` |
| `candidate_id` | string / integer | ○ | 候補日ID |

### 参加表明

```json
{
  "action": "join",
  "candidate_id": "10"
}
```

### 参加キャンセル

```json
{
  "action": "cancel",
  "candidate_id": "10"
}
```

## 認証・利用条件

以下をすべて満たす必要がある。

- Standup Spot にログインしている
- `users.status` が `active`
- `member` ロールが `active`
- `comedian` ロールが `active`
- `standup_comedians.status` が `active`

条件を満たさない場合は、以下のエラーを返す。

- 未ログイン、またはユーザーが無効：`401 Unauthorized`
- 有効なコメディアンではない：`403 Forbidden`

## 処理内容

### 共通処理

1. リクエストボディをJSONとして読み込む。
2. `action` が `join` または `cancel` であることを確認する。
3. `candidate_id` が1以上の整数として扱える値であることを確認する。
4. `standup_event_candidates` に対象候補日が存在することを確認する。

候補日が存在しない場合は `404 Candidate not found` を返す。

### action: join

対象候補日に対して参加表明を登録する。

- `standup_event_candidate_comedians` に `candidate_id` と `comedian_id` を登録する。
- すでに同じ組み合わせが登録されている場合は、重複登録しない。
- すでに参加表明済みの場合も成功として扱う。

### action: cancel

対象候補日に対する参加表明を削除する。

- `candidate_id` と `comedian_id` が一致するレコードを削除する。
- 対象レコードが存在しない場合も成功として扱う。

## レスポンス

成功時は以下を返す。

```json
{
  "success": true,
  "action": "join",
  "candidate_id": "10",
  "comedian_id": "5"
}
```

`action` には実行した操作、`candidate_id` と `comedian_id` には対象IDが文字列で返る。

## エラー

| HTTPステータス | 内容 |
|---|---|
| `400` | リクエストボディ不正、action不正、候補日ID不正 |
| `401` | 未ログイン、またはユーザーが無効 |
| `403` | 有効なコメディアンではない |
| `404` | 候補日が存在しない |
| `405` | POST以外のメソッド |
| `500` | 内部エラー |

## 備考

- 参加表明・キャンセルの対象は、ログインユーザー自身のコメディアンIDに限られる。
- イベントの `status` による制限は行わない。
- 候補日の日時による制限は行わない。
- 参加表明済みの候補日に再度 `join` しても、重複登録されない。
- 参加表明していない候補日に `cancel` しても、成功として扱う。
- 候補日が削除された場合、参加表明レコードもDBの外部キー制約によって削除される。