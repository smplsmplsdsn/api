# 事前準備

## ローカル環境構築

- [環境構築](readme/environment.md)
- [Googleログイン設定手順](readme/login-google.md)

## トラブルシューティング

- [Docker](readme/docker.md)


# Standup Spot Documentation

Standup Spot の API・DATABASE・開発環境に関する仕様書を管理するディレクトリ。

## Documentation

### API

APIのエンドポイント、認証、リクエスト、レスポンス、処理条件などを定義する。

- [API仕様書一覧](readme/api/README.md)

### DATABASE

データベースのテーブル構造、カラム、制約、テーブル間の関連などを定義する。

- [DATABASE仕様書一覧](readme/database/README.md)

### Setup

開発環境、Docker、認証サービスなどの環境構築・運用に関する情報を管理する。

- [環境構築](readme/setup/environment.md)
- [Docker](readme/setup/docker.md)
- [Google OAuth](readme/setup/google-oauth.md)

## Documentation Policy

仕様書は、原則として現在のPHP・SQLの実装を正とする。

- 実装と既存仕様書に差異がある場合は、実装に合わせて仕様書を更新する。
- 実装上の問題を発見した場合は、仕様書を変更して正当化せず、修正候補として別途管理する。
- 実装から判断できない事項は推測せず、確認してから記載する。
- 仕様変更が発生した場合は、関連する仕様書も更新する。

