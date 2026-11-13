# Authentication API

Standup Spot APIの認証・ログイン状態管理に関する仕様。

Google OAuthによるログインを行い、PHPセッションとRemember Tokenによってログイン状態を管理する。

## Overview

認証関連のエンドポイントは以下のとおり。

| Endpoint | Method | Description |
|---|---|---|
| `/auth/google` | GET / POST | Google OAuth認証開始 |
| `/auth/google/callback` | GET / POST | Google OAuthコールバック |
| `/me` | GET / POST | ログインユーザー情報取得 |
| `/logout` | GET / POST | ログアウト |

## 1. Google OAuth

### 1.1 Authentication Flow

Google OAuth 2.0を利用してユーザー認証を行う。

処理の流れ：

1. `/auth/google` にアクセスする。
2. 対象サービスを確認する。
3. OAuth用の `state` を生成する。
4. セッションに `state` とサービス識別子を保存する。
5. Googleの認証画面へリダイレクトする。
6. Googleから `/auth/google/callback` に戻る。
7. `state` を検証する。
8. 認証コードをGoogleへ送信し、ID Tokenを取得する。
9. Googleアカウントのユーザー識別子を取得する。
10. DBから既存ユーザーを検索する。
11. 既存ユーザーの場合はログイン処理、新規ユーザーの場合はユーザー登録を行う。
12. セッションIDを再生成する。
13. Remember Tokenを発行する。
14. 対象サービスの画面へリダイレクトする。

### 1.2 Request

```text
/auth/google?service=standup
```

| Parameter | Required | Description |
|---|---|---|
| `service` | No | サービス識別子。省略時は `demo` |

`service` はGETまたはPOSTから取得する。

有効なサービス：

- `demo`
- `standup`
- `stations`

空文字の場合や無効なサービスの場合はエラーを返す。

### 1.3 OAuth Parameters

Googleへ送信する主なパラメータ：

| Parameter | Value |
|---|---|
| `client_id` | サービス別のGoogle Client ID |
| `redirect_uri` | サービス別のCallback URL |
| `response_type` | `code` |
| `scope` | `openid` |
| `state` | ランダム生成した値 |
| `prompt` | `select_account` |

`state` は32文字の16進数文字列を生成し、セッションに保存する。

Callback時には `hash_equals()` によって一致を確認する。

### 1.4 Callback

GoogleからのCallbackを受け付ける。

```text
/auth/google/callback?code=...&state=...
```

認証コードをGoogleのToken Endpointへ送信する。

```text
https://oauth2.googleapis.com/token
```

取得したID TokenからGoogle側のユーザー識別子 `sub` を取得する。

この識別子を以下の組み合わせで検索する。

- `user_auth.provider = google`
- `user_auth.provider_user_id = Googleのsub`

### 1.5 Existing User

既存ユーザーの場合：

1. `user_auth` からユーザーIDを取得する。
2. 対象サービスの `member` ロールを有効化する。
3. セッションIDを再生成する。
4. セッションにユーザーIDを保存する。
5. 現在のCookieに対応するRemember Tokenを削除する。
6. 新しいRemember Tokenを発行する。
7. 対象サービスの画面へリダイレクトする。

### 1.6 New User

新規ユーザーの場合、以下のレコードを作成する。

1. `users`
2. `user_auth`
3. `user_service_roles`

`users.public_id` はULIDを生成して登録する。

`users.status` は `active` とする。

対象サービスの `member` ロールも `active` として登録する。

ユーザー登録処理はトランザクション内で実行する。

登録完了後、既存ユーザーと同様にセッションIDを再生成し、Remember Tokenを発行する。

### 1.7 Redirect

認証完了後のリダイレクト先は、環境とサービス識別子によって決定する。

現在のStandup Spotの設定：

| Environment | Redirect URL |
|---|---|
| Production | `https://standupspot.com/?view=mypage` |
| Local | `http://localhost:8090/?view=mypage` |

---

## 2. Session Management

### 2.1 Session Configuration

PHPセッションを使用してログイン状態を管理する。

| Setting | Value |
|---|---|
| Cookie lifetime | 90 days |
| Session GC max lifetime | 90 days |
| Path | `/` |
| HttpOnly | `true` |
| Secure | `SESSION_COOKIE_SECURE` による |
| SameSite | Secure有効時 `None`、無効時 `Lax` |

CookieのDomainは明示的に指定しない。

### 2.2 Session Data

ユーザーIDはサービス単位で管理する。

```php
$_SESSION['services'][$serviceKey]['user_id']
```

例：

```php
$_SESSION['services']['standup']['user_id']
```

同一ユーザーが複数のサービスを利用する場合、それぞれのサービスのログイン状態を独立して管理する。

### 2.3 Session ID Regeneration

Google OAuthによるログイン成功時に、以下を実行する。

```php
session_regenerate_id(true);
```

既存のセッションIDを破棄し、新しいセッションIDを発行する。

---

## 3. Remember Token

### 3.1 Overview

PHPセッションが存在しない場合でも、Remember Tokenによってログイン状態を復元する。

CookieとDBを組み合わせて管理する。

### 3.2 Cookie

| Item | Value |
|---|---|
| Cookie name | `remember_token` |
| Format | `selector.validator` |
| Lifetime | 90 days |
| Path | `/` |
| HttpOnly | `true` |
| Secure | `SESSION_COOKIE_SECURE` による |
| SameSite | Secure有効時 `None`、無効時 `Lax` |

CookieのDomainは明示的に指定しない。

### 3.3 Token Structure

Remember Tokenは以下の2つで構成する。

| Value | Length | Description |
|---|---|---|
| `selector` | 32文字 | トークン検索用識別子 |
| `validator` | 64文字 | 認証用トークン |

どちらもランダム生成した16進数文字列を使用する。

DBにはValidatorそのものを保存せず、SHA-256ハッシュを保存する。

### 3.4 Token Issuance

ログイン成功時にRemember Tokenを発行する。

1. Selectorを生成する。
2. Validatorを生成する。
3. ValidatorのSHA-256ハッシュを生成する。
4. `user_remember_tokens` に保存する。
5. Cookieを発行する。

DBの有効期限は発行日時から90日後とする。

### 3.5 Login Restoration

Remember Tokenによるログイン復元は、対象サービスのセッションが存在しない場合に実行する。

ただし `/logout` では実行しない。

復元時には以下を確認する。

1. Cookieの形式が正しい。
2. Selectorに対応するレコードが存在する。
3. DB上の有効期限内である。
4. `users.status = active` である。
5. 対象サービスの `member` ロールが `active` である。
6. CookieのValidatorとDBのValidator Hashが一致する。

確認に失敗した場合は復元せず、Cookieを削除する。

### 3.6 Token Rotation

ログイン復元に成功した場合、Validatorを更新する。

1. 新しいValidatorを生成する。
2. SHA-256ハッシュを生成する。
3. DBの `validator_hash` を更新する。
4. `expires_at` を90日後に延長する。
5. `last_used_at` を更新する。
6. Cookieを新しいValidatorで再発行する。

更新処理はDBトランザクション内で実行する。

Selectorは変更しない。

### 3.7 Multiple Devices

Remember Tokenは端末ごとに個別に発行する。

同じユーザーが複数端末でログインしている場合、それぞれの端末に対応するレコードを保持する。

ログイン時・ログアウト時には、現在のCookieに対応するSelectorのレコードのみを削除する。

他の端末のRemember Tokenには影響しない。

---

## 4. GET /me

ログイン中のユーザー情報を取得する。

### Request

```text
/me?service=standup
```

| Parameter | Required | Description |
|---|---|---|
| `service` | No | サービス識別子。省略時は `demo` |

### Authentication

対象サービスのセッションにユーザーIDが存在すること。

取得時には以下を確認する。

- ユーザーが存在する。
- 対象サービスの `member` ロールが `active` である。
- `users.status = active` である。

利用できない場合は、対象サービスのセッション情報を削除して401を返す。

### Response

成功時：

```json
{
  "user": {
    "public_id": "01XXXXXXXXXXXXXXX",
    "status": "active",
    "created_at": "2026-01-01 00:00:00"
  }
}
```

実際の `public_id` と `created_at` はDBの値を返す。

### Error

ログインしていない場合：

HTTP 401

```json
{
  "success": false,
  "error": "ログインしていません"
}
```

ユーザーが存在しない、またはサービスを利用できない場合もHTTP 401を返す。

---

## 5. GET /logout

対象サービスのログイン状態を解除する。

### Request

```text
/logout?service=standup
```

| Parameter | Required | Description |
|---|---|---|
| `service` | No | サービス識別子。省略時は `demo` |

`service` はGETまたはPOSTから取得する。

### Process

1. 対象サービスのリダイレクト先を取得する。
2. 現在のCookieに対応するRemember Tokenを削除する。
3. Remember Token Cookieを削除する。
4. 対象サービスのセッション情報を削除する。
5. 対象サービスの画面へリダイレクトする。

他のサービスのセッション情報は削除しない。

他の端末のRemember Tokenも削除しない。

### Redirect

ログアウト完了後、対象サービスの画面へリダイレクトする。

---

## 6. Error Response

共通エラー関数 `apiError()` を使用する場合、以下の形式でJSONを返す。

```json
{
  "success": false,
  "error": "エラーメッセージ"
}
```

HTTPステータスコードは処理内容に応じて設定する。

---

## 7. Related Tables

認証処理で使用する主なテーブル：

| Table | Purpose |
|---|---|
| `users` | ユーザー情報 |
| `user_auth` | Google認証情報 |
| `user_service_roles` | サービス利用権限 |
| `user_remember_tokens` | ログイン継続用トークン |

各テーブルの詳細は [DATABASE Common](../database/common.md) を参照。