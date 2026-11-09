# `/role?service=standup` ロール管理API

## 役割

ログイン中のユーザー本人について、Standup のロールを追加・解除する。

対象となるロールは以下の2種類。

- `comedian`
- `venue_manager`

`member` ロールはこのAPIでは変更できない。

## リクエスト

- **Method:** POST
- **Endpoint:** `/role?service=standup`
- **Content-Type:** `application/json`

### リクエストボディ

```json
{
  "role": "comedian",
  "status": "active"
}
```

| Key | Type | Required | Description |
|---|---|---|---|
| `role` | string | 必須 | 変更するロール |
| `status` | string | 必須 | 変更後の状態 |

### 許可される値

| Key | 許可される値 |
|---|---|
| `role` | `comedian`, `venue_manager` |
| `status` | `active`, `inactive` |

`member` ロールの追加・解除は受け付けない。

対象ユーザーはセッションから取得するため、リクエストにユーザーIDを指定する必要はない。

## 認証・権限

以下の条件をすべて満たす必要がある。

1. ログインしていること
2. `users.status = active` であること
3. Standup の `member` ロールが `active` であること

他のユーザーのロールを変更することはできない。

## 処理内容

ロールの追加・更新は `user_service_roles` に対して行う。

同じユーザー・サービス・ロールのレコードが存在しない場合は新規登録し、存在する場合は `status` を更新する。

更新処理はトランザクション内で実行する。

### comedian ロール

`comedian` ロールの状態変更時には、`standup_comedians` のプロフィール状態も連動して更新する。

#### `active` に変更した場合

1. `user_service_roles` の `comedian` ロールを `active` にする。
2. `standup_comedians` に既存プロフィールがあるか確認する。
3. プロフィールが存在しない場合は、`status = inactive` で新規作成する。
4. プロフィールが存在する場合は、プロフィールの完成条件を再判定する。

プロフィールの完成条件は以下の2つ。

- `name` が空文字ではない
- `thumbnail` が空文字ではない

両方を満たす場合は `active`、満たさない場合は `inactive` にする。

#### `inactive` に変更した場合

1. `user_service_roles` の `comedian` ロールを `inactive` にする。
2. `standup_comedians` のプロフィールが存在する場合は `inactive` にする。
3. 名前・サムネイルなどのプロフィール情報は削除しない。

### venue_manager ロール

`venue_manager` ロールは `user_service_roles` の状態のみを変更する。

- `active`：有効化
- `inactive`：無効化

`standup_comedians` のプロフィール状態には影響しない。

## レスポンス

### 成功

HTTP 200

```json
{
  "success": true,
  "role": "comedian",
  "status": "active"
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `role` | string | 変更したロール |
| `status` | string | 変更後の状態 |

### エラー

エラー時は以下の形式で返す。

```json
{
  "success": false,
  "error": "エラーメッセージ"
}
```

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 400 | `Invalid request body` | JSONが不正、またはリクエストボディが配列として解釈できない |
| 400 | `Invalid role` | 許可されていないロール |
| 400 | `Invalid status` | 許可されていない状態 |
| 401 | `Not logged in` | セッションにユーザーIDが存在しない |
| 401 | `Invalid user` | ユーザーが存在しない、または `users.status` が `active` ではない |
| 403 | `Member role is inactive` | Standup の `member` ロールが有効ではない |
| 500 | `Internal server error` | 内部エラー |

内部エラーの詳細はレスポンスに含めず、サーバーログに記録する。

トランザクション中にエラーが発生した場合はロールバックする。

## 補足

- ロール解除時も `user_service_roles` のレコードは削除せず、`status` を `inactive` に更新する。
- 再度 `active` に変更した場合は既存レコードを再利用する。
- `comedian` ロールの再有効化時には、プロフィールの完成条件を再判定する。
- プロフィールが `inactive` の場合でも、`comedian` ロール自体が `active` であれば、プロフィールの名前・サムネイルを編集するための機能は利用できる。
- `hasActiveRole()` は、対象ロールだけでなく `member` ロールも `active` であることを確認する共通関数。
- `getActiveComedianId()` は、有効な `member` ロールと `comedian` ロール、および有効なコメディアンプロフィールを確認してIDを返す。