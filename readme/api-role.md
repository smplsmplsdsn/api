# Standup API：ロール管理仕様

## 1. ロール構成

Standupでは以下の3ロールを使用する。

* `member`
* `comedian`
* `venue_manager`

### member

Standupを利用する基本ロール。

ユーザーがStandupに初めて参加した時点で自動的に以下を登録する。

```text
service_key = standup
role        = member
status      = active
```

`member` はユーザー自身が追加・削除する対象ではない。

### comedian

Standupで芸人として活動するためのロール。

ユーザー自身が自分に追加・解除できる。

### venue_manager

Standupで会場関連の管理を行うためのロール。

ユーザー自身が自分に追加・解除できる。

現時点では会場との個別の紐付けは行わず、`user_service_roles` のロールだけで管理する。

---

## 2. ロール変更の権限

ユーザーが変更できるのは**自分自身のロールだけ**とする。

### 変更可能

```text
自分
├─ comedian → 追加・解除可能
└─ venue_manager → 追加・解除可能
```

### 変更不可

```text
A → Bをcomedianにする
A → Bのvenue_managerを変更する
```

他ユーザーのロールを変更するAPIは作成しない。

現時点で他ユーザーのロールを変更する必要がある場合は、DBを直接操作する。

そのため、現段階では`admin`ロールを用意しない。

---

## 3. ロール管理APIではユーザーIDを指定しない

自分自身のロールのみ変更できるため、ロール変更APIでは`user_id`をリクエストパラメータとして受け取らない。

現在ログインしているユーザーをセッションから取得する。

```text
session
  ↓
service_key = standup
  ↓
現在のuser_id
```

DB内部では`users.id`を使用する。

外部APIでユーザーを識別する必要がある場合は、これまでの方針どおり`users.public_id`を使用する。

---

## 4. user_service_roles

既存の`user_service_roles`テーブルを使用する。

```text
user_service_roles
- id
- user_id
- service_key
- role
- status
- created_at
- updated_at
```

1ユーザーが同じサービスで複数のロールを持つことを許可する。

例えば、

```text
user_id = 10
service_key = standup
role = member
status = active
```

と、

```text
user_id = 10
service_key = standup
role = comedian
status = active
```

を同時に保持できる。

---

## 5. ロールの有効・無効

ロールを削除するのではなく、`status`を変更する。

例えば、

```text
comedian / active
```

を解除すると、

```text
comedian / inactive
```

に変更する。

レコード自体は削除しない。

再度comedianになる場合は、

```text
comedian / inactive
        ↓
comedian / active
```

に戻す。

---

## 6. comedianとstandup_comediansは別管理

`user_service_roles`の`comedian`ロールと、`standup_comedians.status`は**連動させない**。

それぞれ意味が異なる。

### user_service_roles

```text
comedian / active
```

意味：

> このユーザーはStandupでcomedianという役割を持っている。

### standup_comedians

```text
status = active
```

意味：

> このcomedianプロフィールは有効な状態である。

---

## 7. 有効なcomedianの判定

API上で「有効なcomedian」として扱う条件は、以下の両方を満たすこと。

### user_service_roles

```text
service_key = 'standup'
role        = 'comedian'
status      = 'active'
```

### standup_comedians

```text
user_id = 対象ユーザー
status  = 'active'
```

つまり、

```text
comedian role    active
comedian profile active
```

の両方が成立した場合のみ、有効なcomedianとして扱う。

---

## 8. comedianロールを新規追加した場合

ユーザーが自分自身にcomedianロールを追加する。

まず`user_service_roles`を確認する。

### 既存レコードがない場合

以下を新規作成する。

```text
service_key = standup
role        = comedian
status      = active
```

その後、`standup_comedians`を確認する。

### standup_comediansが存在しない場合

以下のレコードを新規作成する。

```text
user_id = 自分
status  = inactive
```

新規作成時点ではプロフィールを有効にしない。

---

## 9. standup_comediansがすでに存在する場合

例えば以下のレコードが既に存在する場合、

```text
standup_comedians
user_id = 自分
status  = inactive
```

comedianロールを追加しても、変更するのは`user_service_roles`のみとする。

```text
user_service_roles
comedian = active
```

`standup_comedians`は、

```text
inactive
```

のまま。

したがって、以下の状態は正常な状態として扱う。

```text
comedian role    active
comedian profile inactive
```

---

## 10. comedianプロフィールをactiveにする条件

現在はサムネイルアップロード機能が未実装のため、以下を条件とする。

```text
nameに値が入っている
```

つまり、

```text
standup_comedians.name != ''
```

となった場合に、

```text
standup_comedians.status = active
```

とする。

### 将来

サムネイルアップロード機能が完成した場合、active条件を以下に変更する。

```text
name != ''
AND
thumbnail != ''
```

ロール管理APIそのものは変更せず、プロフィール編集側のactive判定を変更する。

---

## 11. comedianを解除した場合

例えば現在、

```text
user_service_roles
comedian = active

standup_comedians
status = active
```

のユーザーがcomedianロールを解除する場合、変更するのは`user_service_roles`のみとする。

```text
user_service_roles
comedian = inactive
```

`standup_comedians.status`は変更しない。

結果として、

```text
comedian role    inactive
comedian profile active
```

となる。

この状態は正常な状態として扱う。

---

## 12. comedian再登録時

一度comedianロールを解除したユーザーが再びcomedianになる場合、

```text
user_service_roles
comedian = inactive
```

を、

```text
comedian = active
```

に戻す。

`standup_comedians`は変更しない。

例えば、

```text
comedian role    inactive
comedian profile active
```

から、

```text
comedian role    active
comedian profile active
```

となる。

これにより、過去に完成させたプロフィールを再度作成する必要はない。

---

## 13. comedianの状態遷移

### 新規登録

```text
member
   ↓
comedian role = active
profile       = inactive
```

プロフィールを編集し、active条件を満たす。

```text
comedian role = active
profile       = active
```

この状態になると有効なcomedianとして各種機能を利用できる。

### comedianを解除

```text
comedian role = active
profile       = active
```

↓

```text
comedian role = inactive
profile       = active
```

### 再びcomedianになる

```text
comedian role = inactive
profile       = active
```

↓

```text
comedian role = active
profile       = active
```

---

## 14. comedianプロフィール編集

`comedian`ロールがactiveであれば、プロフィール準備中でも以下を利用できる。

* 名前の登録・編集
* サムネイルの登録・編集

### プロフィールがactiveになった後

以下の機能を利用できる。

* 各ソーシャルメディアの登録・編集
* イベントの作成
* イベントの編集
* 会場の作成
* 会場の編集
* 候補イベントへの参加登録
* 候補イベントへの参加取り消し
* コメント作成
* コメント編集
* コメント削除

---

## 15. venue_manager

`venue_manager`は現時点では`user_service_roles`のみで管理する。

```text
service_key = standup
role        = venue_manager
status      = active
```

ユーザー自身が追加・解除できる。

現時点では、会場との個別の紐付けは行わない。

将来的に、

```text
venue_manager
    ↓
特定のvenueのみ管理可能
```

という要件が必要になった場合、その時点で別途会場との関連テーブルを設計する。

---

## 16. 権限チェック用の共通関数

今後のAPIで権限判定を共通化する。

### ロールそのものを確認

```php
hasActiveRole($user_id, $service_key, $role)
```

例えば、

```php
hasActiveRole($user_id, 'standup', 'comedian')
```

では、`user_service_roles`のactive状態のみを確認する。

主に以下で使用する。

* comedianロール追加
* comedianロール解除
* 名前編集
* サムネイル編集

---

### 有効なcomedianを確認

```php
isActiveComedian($user_id)
```

内部で以下を確認する。

```text
user_service_roles
comedian / active

AND

standup_comedians
status = active
```

主に以下で使用する。

* ソーシャルメディア編集
* イベント作成
* イベント編集
* 会場作成
* 会場編集
* 候補イベント参加登録
* 候補イベント参加取り消し
* コメント作成
* コメント編集
* コメント削除

---

## 17. ロールとcomedianプロフィールの関係

最終的な考え方は以下。

```text
user_service_roles
        │
        │ 権限
        ▼
   comedian role
        │
        │ active
        ▼
standup_comedians
        │
        │ プロフィール状態
        ▼
 active / inactive
```

`user_service_roles`は**権限**を管理し、`standup_comedians.status`は**comedianプロフィールの有効状態**を管理する。

この2つは独立して管理する。

ただし、新規comedianロール追加時に`standup_comedians`が存在しない場合のみ、`inactive`状態でプロフィールレコードを作成する。

---

## 18. 最終的な状態一覧

| comedian role | comedian profile | 状態                      |
| ------------- | ---------------- | ----------------------- |
| なし            | なし               | memberのみ                |
| active        | inactive         | comedian登録済み・プロフィール準備中  |
| active        | active           | **有効なcomedian**         |
| inactive      | active           | comedian活動停止中・プロフィール保持  |
| inactive      | inactive         | comedian活動停止中・プロフィール未有効 |

`active / active`のみを「有効なcomedian」として扱う。

---

## 19. 今後のAPI実装方針

ロール管理を起点として、以下の順番で実装する。

1. ロール管理
2. コメント投稿
3. プロフィール編集
4. イベント作成
5. イベント編集・候補管理
6. 出演芸人管理
7. 全APIの権限・バリデーション総点検

各APIでは、共通の権限チェック関数を使用し、APIごとに個別のロール判定を重複して実装しない。

---

## 20. ロール管理APIの基本方針

ロール管理APIでは、ログイン中のユーザー自身を対象とする。

変更対象となるロールは以下の2つ。

```text
comedian
venue_manager
```

`member`はStandup参加時に自動付与される基本ロールとする。

他ユーザーのロールを変更するAPIは作成しない。

comedianロールの追加時のみ、`standup_comedians`が存在しなければ`inactive`状態で作成する。

それ以外の`user_service_roles`と`standup_comedians`のstatus変更は独立して行う。

この設計により、**「ロールとしての権限」と「プロフィールとしての有効状態」を明確に分離しつつ、既存プロフィールを保持したままcomedian活動を再開できる構成とする。**
