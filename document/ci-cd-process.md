# CI/CDプロセス

このリポジトリのCI/CDは、**GitHub Actions（CI）** と **Render（CD）** を使って構成されています。

## 1. CI（GitHub Actions）

ワークフローは `.github/workflows/backend-ci.yml` で管理されています。

### トリガー
- `main` ブランチへの `push`
- `main` ブランチ向けの `pull_request`

### 実行内容
1. リポジトリをチェックアウト
2. PHP環境をセットアップ（PHP 8.3）
3. Composer依存キャッシュを復元
4. Composer依存をインストール
5. テスト用 `.env` を作成（SQLite利用）
6. マイグレーションを実行
7. テストを実行（`php artisan test --stop-on-failure --testdox`）

### 目的
- 変更が `main` に入る前に、最低限の動作保証（マイグレーションとテスト）を自動確認する

---

## 2. CD（Render）

Render設定は `render.yaml` と `Dockerfile` で管理されています。

### デプロイ対象
- `main` ブランチ

### デプロイ方式
- `env: docker` を利用
- `Dockerfile` に従ってイメージをビルド

### コンテナ起動フロー
1. コンテナ起動時に `php artisan migrate --force` を実行
2. 続けて `php artisan serve --host=0.0.0.0 --port=10000` でアプリを起動

### 目的
- `main` への反映後、自動で本番環境へデプロイし、運用をシンプルに保つ

---

## 3. 開発フロー（推奨）

1. 機能開発用ブランチを作成
2. 実装・ローカル確認
3. Pull Request作成
4. GitHub ActionsのCI成功を確認
5. レビュー後に `main` へマージ
6. Renderによる自動デプロイを確認

---

## 4. 運用時の確認ポイント

- CI失敗時は、まず `composer install` / `.env` / DB設定（SQLite）を確認する
- マイグレーション失敗時は、適用済みmigrationとの差分とDB接続情報を確認する
- デプロイ後は主要APIの疎通確認（ヘルスチェック相当）を実施する

