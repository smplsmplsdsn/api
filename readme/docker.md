# Docker

##  compose.yaml や Dockerfile を修正した後にすること

修正した内容によって多少異なる

### 1. 基本（コンテナの再作成）

設定変更・環境変数・ポート・ボリュームなど、設定や構成のみ、compose.yaml を変更した場合はコンテナを再作成する。

```text
docker compose up -d
```

### 2. Docker修正/パッケージ追加（イメージの再ビルド）

Dockerfile を書き換えた場合や、compose.yaml 内の build 設定を変更した場合は、イメージを再ビルドする。

```text
docker compose up -d --build
```

### 3. 一度完全に綺麗にして立ち上げ直したい場合（確実な方法）

キャッシュや古いコンテナの状態が残ってうまくいかない場合や、安全にリセットしたい場合は一度停止・削除してから起動します。

```text
# 1. コンテナを停止・削除
docker compose down

# 2. 再起動（ビルドも含む場合）
docker compose up -d --build
```

### 例外：設定が反映されない・古い状態が残る場合

既存のボリューム（DBのデータなど以外で永続化されたデータ）やビルドキャッシュが原因の可能性がある。<br>
その場合は<br>
docker compose down -v（ボリューム削除※データ消去注意）<br>
や<br>
docker compose build --no-cache<br>
を試してみる。
