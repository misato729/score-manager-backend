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
1. `php -v` で起動するPHPのバージョンをログへ出力
2. `php artisan migrate --force` を実行
3. 続けて `php artisan serve --host=0.0.0.0 --port=10000` でアプリを起動

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

## 5. PHP本体のセキュリティ更新（2026年10月21日）

### 公開前の確認

2026年10月21日22:20（日本時間）時点の[PHP公式告知](https://www.php.net/archive/2026.php#2026-10-20-1)では、CVSS v4.0 9.2の脆弱性2件を修正するリリースは同日23:00を予定していた。対象バージョン・修正版番号・詳細はリリース時の公開予定で、この公開前時点ではアプリへの影響や特定の設定による回避の有効性を確定できなかった。

### 23:03時点の公開確認

- [PHP公式発表](https://www.php.net/archive/2026.php)で修正版 **8.3.36** の公開を確認した。対象はWebリクエストを処理するPHP環境で、このプロジェクトもPHP本体の更新が必要となる。
- [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) と [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp) は、8.3系では8.3.36未満が影響を受ける。どちらも認証なしのリモートコード実行につながる。設定だけによる完全な回避策はなく、PHP本体の更新が必要となる。
- Docker公式のビルド元 `versions.json` は8.3.35のまま、公開レジストリの `php:8.3.36-fpm` と `php:8.3.36-cli` はいずれもHTTP 404（`MANIFEST_UNKNOWN`）だった。イメージ配布待ちのため、PHPの指定変更と修正版でのテストは未実施。
- ユーザーの申告ではRenderのWebサービスは一時停止（suspend）中。停止状態を維持し、未配布のイメージや旧PHPのままサービスを再開しない。本番の停止中の応答失敗は新たな障害と扱わない。
- [RenderのDeploy Hooks仕様](https://render.com/docs/deploy-hooks)では、suspend中のサービスへのデプロイ要求はHTTP 409となる。停止中に先に修正版を配備できるとは限らないため、修正版を安全に反映する方法を確認してから再開する。

### 10月22日00:59時点の配布確認

Dockerの配布定義には8.3.36が反映され、公開レジストリで `php:8.3.36-fpm`、`php:8.3.36-cli`、`php:8.3.36-fpm-bookworm` がHTTP 200を返すようになった。ただし、確認したイメージ一覧は空で、`docker buildx imagetools inspect php:8.3.36-fpm` でも `Manifests` に実行用イメージが存在しなかった。タグの存在やHTTP 200だけでは配布完了と判断しない。

この時点では、実行環境用イメージの配布と取得後の `php -v` 確認まで設定変更・修正版でのテストを保留した。

### 10月22日02:03時点の修正・検証結果

- `php:8.3.36-fpm` の実行用イメージが配布されたことを確認し、linux/amd64版を取得した。ネットワークを遮断したコンテナで実際の `PHP_VERSION` が **8.3.36** であることを確認した。
- 取得したイメージのdigestは `sha256:a2d39885e245cdf5120b9eafada552cd49c73edc53285179148dd3e2ec42c2a6`。
- `Dockerfile` とCI・店舗同期のPHP指定を8.3.36へ固定した。起動時のPHP版ログを維持し、Composerの依存指定には今回の追加変更を行っていない。
- `.env`・Git情報・既存vendor・DB実データ・キャッシュ・ログを除いた一時コンテキストで、本番用Dockerfileと同じビルド工程（本番依存のインストール、キャッシュクリアを含む）が成功した。
- 同じイメージへ開発依存を追加し、`--network none` とSQLiteメモリDBで検証した。必要拡張と `composer check-platform-reqs` は成功し、PHPUnitは **106件・392アサーション成功、非推奨警告3件**。警告数は更新前の事前テストと同じだった。
- テスト対象は、店舗通知機能などの既存の未コミット変更を含む現在の作業ツリー。PHP対応だけを本番へ反映する場合は、反映するコミットのCIも確認する。
- GitHub Actions上の実行、本番へのデプロイ、Renderの再開は未実施。本番の稼働PHP版を修正済みと確認したわけではない。サービスの停止状態を維持する。

以下は、修正後のリポジトリでの指定を示す。本番への反映は別作業となる。

| 環境 | 現在の指定 | 更新時の確認 |
| --- | --- | --- |
| Render本番 | `Dockerfile` の `php:8.3.36-fpm` | 本番への反映は未実施。修正版で再ビルド・再デプロイし、新しい起動ログの `php -v` を確認する |
| GitHub Actions CI | `backend-ci.yml` の `php-version: '8.3.36'` | 反映するコミットでのCI成功を確認する |
| GitHub Actions店舗同期 | `sync-konami-shops.yml` の `php-version: '8.3.36'` | 修正版のセットアップを確認する。確認のために本番DBへの同期を実行する必要はない |
| ローカル | PHP 8.4.7（Homebrew、確認時点） | 本番とは別にPHP 8.4の修正版を確認して更新する |

`composer.json` の `php: ^8.2` と `config.platform.php: 8.2.0` は依存解決の条件であり、PHP本体をインストール・更新する指定ではない。今回の対応のために `composer update` は実行しない。

### 修正版公開後

1. PHP公式で対象バージョンと修正版番号を確認する。PHP 8.3が対象であれば、同じ8.3系の修正版を選ぶ。
2. [Docker公式PHPイメージ](https://hub.docker.com/_/php)で修正版の配布を確認する。PHP本体の公開時刻とDockerイメージの配布時刻は一致するとは限らない。対象の `php:<修正版>-fpm` を取得し、`docker run --rm php:<修正版>-fpm php -v` で実際のバージョンを確認する。
3. `Dockerfile` と両GitHub Actionsの指定を確認済みの修正パッチ版へ更新する。既存の店舗通知機能などの未コミット変更を混ぜず、PHP対応の差分だけをレビュー・反映する。
4. 修正版のPHPで既存のPHPUnitとComposerの実行環境要件を確認する。ローカルの旧PHPでのテスト成功は、修正版での検証に代えない。
5. デプロイ直前に本番DBの復旧手段と適用予定のマイグレーションを確認する。このイメージは起動時に `migrate --force` を実行する。
6. Renderで修正版を含むコミットを再ビルド・再デプロイする。現在はsuspend中のため、停止中のデプロイ可否と再開時に起動するイメージを確認してから反映方法を決める。旧イメージのまま単純に再開しない。キャッシュが疑わしい場合は[Clear build cache & deploy](https://render.com/docs/deploys#manual-deploys)を使う。単なるサービス再起動では既存イメージのPHPは更新されない。
7. 新しいデプロイの起動ログで修正済みのPHPパッチ版を確認する。`/api/health` のHTTP 200と `{"ok":true}`、公開の店舗一覧、ログイン画面、認証後のスコア参照を確認する。HTTP 200だけではPHPの修正適用済みとは判断しない。

ローカルでの事前テストは、設定キャッシュがないことを確認し、外部DBや通知先を使わない設定で実行する。

```bash
APP_ENV=testing APP_DEBUG=false \
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
DB_URL= DB_CONNECTION=sqlite DB_DATABASE=:memory: \
CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
MAIL_MAILER=array SHOP_SYNC_SENTRY_DSN= \
php vendor/bin/phpunit --no-progress
```

デプロイ失敗時は、脆弱な旧イメージへ戻して対応完了としない。公開済みの公式回避策やアクセス制限・一時停止の必要性を確認し、修正版での復旧を進める。

