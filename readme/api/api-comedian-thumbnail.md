# `/comedian_thumbnail?service=standup` コメディアンサムネイルアップロードAPI

## 役割

ログイン中のユーザー本人のコメディアンサムネイル画像をアップロードする。

アップロードした画像は仮保存され、`standup_comedians.thumbnail_temp` にパスを登録する。

正式なサムネイルへの反映は `/comedian` の POST で行う。

## リクエスト

- **Method:** POST
- **Endpoint:** `/comedian_thumbnail?service=standup`
- **Content-Type:** `multipart/form-data`

### リクエストパラメータ

| Key | Type | Required | Description |
|---|---|---|---|
| `thumbnail` | file | 必須 | アップロードする画像ファイル |

## 認証・権限

以下の条件を満たす必要がある。

1. ログインしていること
2. `users.status = active` であること
3. Standup の `member` ロールが `active` であること
4. Standup の `comedian` ロールが `active` であること
5. `standup_comedians` に本人のプロフィールが存在すること

対象ユーザーはセッションから取得するため、リクエストにユーザーIDを指定する必要はない。

プロフィール自体の `status` は判定条件に含まれない。プロフィールが `inactive` の場合でも、ロールが有効であればアップロードできる。

## 画像の保存

### 保存先

本人のユーザー公開IDを使用した以下のディレクトリに保存する。

```text
/upload/{user_public_id}/standup/profile/
```

### ファイル名

ULIDを使用してファイル名を生成する。

画像の形式に応じて拡張子を付ける。

- JPEG：`.jpg`
- PNG：`.png`
- WebP：`.webp`

### 対応画像形式

アップロード時に以下のMIMEタイプを許可する。

- `image/jpeg`
- `image/png`
- `image/webp`
- `image/heic`
- `image/heif`

HEIC / HEIF は画像処理環境によってJPEGに変換される。保存されるファイル形式は処理後のMIMEタイプによって決まる。

### 画像加工

画像保存時に以下の加工を行う。

| 項目 | 設定 |
|---|---|
| 最大辺 | 400px |
| 加工後の最大ファイルサイズ | 512KB |
| EXIFの向き | 補正 |
| メタデータ | 削除（ICCプロファイルは可能な場合に保持） |

最大辺が400pxを超える場合は縮小する。画像の縦横比は維持する。

JPEG・WebPは指定サイズ以内に収まるよう画質を調整する。PNGは画質圧縮の対象外。

元画像のサイズや画素数にも制限があり、最大辺12,000px以下、総画素数7,000万画素以下の画像が対象となる。

## DB更新

画像の保存に成功した後、`standup_comedians` を更新する。

更新項目：

- `thumbnail_temp`：アップロードした画像の公開パス
- `updated_at`：現在日時

既存の正式な `thumbnail` はこのAPIでは変更しない。

DB更新に失敗した場合は、今回アップロードした画像ファイルを削除する。

## レスポンス

### 成功

HTTP 200

```json
{
  "success": true,
  "comedian": {
    "thumbnail_temp": "/{user_public_id}/standup/profile/{ulid}.jpg"
  }
}
```

| Key | Type | Description |
|---|---|---|
| `success` | boolean | 処理結果 |
| `comedian.thumbnail_temp` | string | 仮保存した画像の公開パス |

## エラー

エラー時は以下の形式で返す。

```json
{
  "success": false,
  "error": "エラーメッセージ"
}
```

### API固有のエラー

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 401 | `ログインしてください` | セッションにユーザーIDが存在しない |
| 401 | `ユーザーが見つかりません` | ユーザーが存在しない |
| 403 | `ユーザーが無効です` | `users.status` が `active` ではない |
| 403 | `コメディアン権限がありません` | 有効なコメディアンロールがない |
| 404 | `コメディアン情報が見つかりません` | コメディアンプロフィールが存在しない |
| 405 | `POSTでアクセスしてください` | POST以外のメソッド |
| 413 | `POST_TOO_LARGE` | POST全体のサイズが `post_max_size` を超えている |
| 500 | `画像を保存できませんでした` | 画像保存処理で例外が発生した |
| 500 | `プロフィールを更新できませんでした` | DB更新に失敗した |
| 500 | `Internal server error` | その他の内部エラー |

### 画像処理で発生するエラー

画像処理共通関数から、以下のエラーが返される場合がある。

| HTTP Status | Error | 発生条件 |
|---|---|---|
| 400 | `画像がありません` | `thumbnail` が送信されていない |
| 400 | `UPLOAD_FAILED` | ファイルアップロードに失敗した |
| 400 | `INVALID_UPLOAD` | HTTPアップロードされたファイルではない |
| 400 | `UNSUPPORTED_IMAGE_TYPE` | 対応していない画像形式 |
| 400 | `IMAGE_PROCESS_FAILED` | 画像処理に失敗した |
| 400 | `IMAGE_TOO_LARGE` | 加工後も最大サイズを超えている |
| 413 | `UPLOAD_TOO_LARGE` | `upload_max_filesize` を超えている |

その他、画像処理・保存時のディレクトリ作成や書き込みなどに関するエラーも返される場合がある。

## 補足

- このAPIでは仮画像のアップロードのみを行う。
- 正式なサムネイルへの反映は `/comedian` の POST で行う。
- アップロードした画像は、プロフィール更新が完了するまで `thumbnail_temp` に保持される。
- 既存の正式なサムネイルはアップロード時点では変更されない。
- 画像ファイルの保存に成功しても、DB更新に失敗した場合は保存したファイルを削除する。