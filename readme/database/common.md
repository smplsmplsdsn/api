# Common DATABASE

ユーザー、外部認証、サービスごとのロール、ログイン継続用トークンを管理するテーブル群。

## Table List

| Table | Description |
|---|---|
| `users` | ユーザー情報 |
| `user_auth` | 外部認証情報 |
| `user_service_roles` | サービスごとのロール管理 |
| `user_remember_tokens` | ログイン継続用トークン |

---

## users

ユーザーの基本情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 内部ユーザーID |
| `public_id` | CHAR(26) | NO | - | 外部公開用ユーザーID（ULID） |
| `nickname` | VARCHAR(100) | NO | `''` | ニックネーム |
| `status` | VARCHAR(20) | NO | `active` | ユーザー状態 |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`public_id`（`uq_users_public_id`）

### Status

| Value | Description |
|---|---|
| `active` | 有効 |
| `inactive` | 無効 |

ユーザーが無効の場合、ログイン中であってもサービスの利用はできない。

---

## user_auth

Googleなどの外部認証サービスとユーザーを紐付ける。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | レコードID |
| `user_id` | BIGINT UNSIGNED | NO | - | `users.id` |
| `provider` | VARCHAR(50) | NO | - | 認証プロバイダー |
| `provider_user_id` | VARCHAR(255) | NO | - | プロバイダー側のユーザーID |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`provider`, `provider_user_id`（`uq_provider_user`）
- FOREIGN KEY：`user_id` → `users.id`
- ON DELETE：CASCADE

### Provider

現在の実装ではGoogle認証を使用する。

| Value | Description |
|---|---|
| `google` | Google OAuth |

`provider_user_id` にはGoogle側のユーザー識別子を保存する。

1ユーザーに複数の外部認証情報を紐付けることができる。

---

## user_service_roles

ユーザーが各サービスを利用するためのロールを管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | レコードID |
| `user_id` | BIGINT UNSIGNED | NO | - | `users.id` |
| `service_key` | VARCHAR(50) | NO | - | サービス識別子 |
| `role` | VARCHAR(50) | NO | - | ロール |
| `status` | VARCHAR(20) | NO | `active` | ロール状態 |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | 作成日時 |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | 更新日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`user_id`, `service_key`, `role`（`uq_user_service_role`）
- FOREIGN KEY：`user_id` → `users.id`
- ON DELETE：CASCADE

### Status

| Value | Description |
|---|---|
| `active` | 有効 |
| `inactive` | 無効 |

### Service Key

サービスを識別するための文字列。

現在のStandup Spotでは `standup` を使用する。

### Roles

Standup Spotで使用するロール。

| Role | Description |
|---|---|
| `member` | サービス利用者 |
| `comedian` | コメディアン |
| `venue_manager` | 会場管理者 |

### Role Management

- ユーザーがサービスに初めてログインすると、`member` ロールが登録される。
- 同じユーザー・サービス・ロールの組み合わせは複数登録できない。
- 無効になったロールはレコードを削除せず、`status` を `inactive` に変更する。
- 再度有効化する場合は、既存レコードの `status` を `active` に変更する。

### Role Authorization

追加ロールの有効判定には、`member` ロールと対象ロールの両方が `active` であることを必要とする。

例：Standup Spotのコメディアンとして有効である条件

- `standup` の `member` ロールが `active`
- `standup` の `comedian` ロールが `active`

ロールの存在だけでなく、両方の状態を確認する。

---

## user_remember_tokens

ログイン状態を継続するためのRemember Tokenを管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | レコードID |
| `user_id` | BIGINT UNSIGNED | NO | - | `users.id` |
| `selector` | CHAR(32) ASCII | NO | - | トークン検索用識別子 |
| `validator_hash` | CHAR(64) ASCII | NO | - | ValidatorのSHA-256ハッシュ |
| `expires_at` | DATETIME | NO | - | 有効期限 |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | 作成日時 |
| `last_used_at` | DATETIME | YES | NULL | 最終使用日時 |

### Constraints

- PRIMARY KEY：`id`
- UNIQUE：`selector`（`uq_selector`）
- INDEX：`user_id`（`idx_user_id`）
- INDEX：`expires_at`（`idx_expires_at`）
- FOREIGN KEY：`user_id` → `users.id`
- ON DELETE：CASCADE

### Token Management

Remember TokenはCookieとデータベースを組み合わせて管理する。

Cookieに保存する値：

```text
selector.validator
```

- `selector`：32文字の16進数
- `validator`：64文字の16進数
- `validator_hash`：ValidatorのSHA-256ハッシュ

データベースにはValidatorそのものを保存せず、ハッシュ値を保存する。

### Lifetime

- 新規発行時の有効期間は90日間。
- ログイン継続に成功するとValidatorを更新する。
- 更新時に有効期限を90日後に延長する。
- 更新時に `last_used_at` を記録する。

### Multiple Devices

端末ごとにRemember Tokenを発行する。

同じユーザーが複数端末でログインした場合、それぞれの端末のトークンを個別に管理する。

### Restore Conditions

ログイン状態の復元時には、以下を確認する。

- Cookieの形式が正しい。
- selectorに対応するレコードが存在する。
- トークンの有効期限内である。
- Validatorのハッシュが一致する。
- `users.status` が `active` である。
- 対象サービスの `member` ロールが `active` である。

復元に成功するとValidatorを更新し、Cookieを再発行する。

---

## Table Relationships

```text
users
  │
  ├── user_auth
  │
  ├── user_service_roles
  │
  └── user_remember_tokens
```

すべての関連テーブルは `users.id` を参照する。

## Deletion

`users` のレコードが削除された場合、以下の関連レコードも外部キー制約によって連鎖削除される。

- `user_auth`
- `user_service_roles`
- `user_remember_tokens`

各テーブルはInnoDBを使用する。