# GASによる毎朝07:30の店舗同期

Google Apps Scriptの無料枠から、既存のGitHub Actionsを起動する。同期・5日連続の不在判定・復帰時の再公開・Sentry通知は引き続きGitHub Actions内で実行する。

## 起動方法

- `tickShopSync` を1分ごとに実行し、日本時間の07:30以降かつ当日未送信ならGitHubへ実行を依頼する。
- 07:30より前には実行しない。GASが遅延しても同日の07:30以降なら追いついて起動する。
- スクリプトロックと当日送信記録で、同時起動や毎分の再実行による重複送信を防ぐ。
- GitHubが依頼を受理した後にだけ送信済みを記録する。APIエラー時は次回以降のトリガーで再試行する。
- 「送信済み」はGitHubの受付成功を意味する。同期の正常終了はGitHub Actionsの結果で確認する。受付後に同期処理が失敗した場合は、自動で毎分再送せず、原因を確認して手動実行する。
- 通信切断などで受付応答を受け取れなかった場合は重複送信の可能性がある。同期本体は同日に複数回実行しても不在日数を増やさない。

GASの[日次トリガーは時刻が揺れ](https://developers.google.com/apps-script/guides/triggers/installable#time-driven_triggers)、[`nearMinute(30)` も前後15分の幅](https://developers.google.com/apps-script/reference/script/clock-trigger-builder#nearMinute(Integer))があるため使用しない。1分間隔のトリガーも厳密な定時実行の保証はない。07:30以降最初のGAS起動で依頼し、その後GitHub runnerの待ち時間が加わる。

## 初回設定

1. [Apps Script](https://script.google.com/home)で、本人だけが編集できる独立したプロジェクト `score-manager-shop-sync` を作成する。
2. `Code.gs` にこのディレクトリの同名ファイルを貼り付ける。プロジェクトの設定でマニフェスト表示を有効にし、`appsscript.json` も同名ファイルの内容に合わせる。タイムゾーンは `Asia/Tokyo`。
3. GitHubで[Fine-grained personal access token](https://github.com/settings/personal-access-tokens/new)を作る。対象リポジトリは **misato729/score-manager-backendだけ**、Repository permissionsの **Actions: Read and write** を許可する。他の追加権限は不要。期限を設定し、期限切れ前に更新する。
4. GASの **プロジェクトの設定 → スクリプト プロパティ** に、キー `GITHUB_TOKEN` とトークンを登録する。トークンをソース・Git・ログ・チャットへ記載しない。プロジェクト編集者はプロパティを読めるため、共有しない。
5. GASエディタから `dispatchShopSyncDryRun` を実行する。初回にGoogleの権限確認が表示される。必要なのは外部APIへの接続と、このスクリプトのトリガー管理だけ。Gmail・Drive全体・DBへの権限は要求しない。
6. GitHub Actionsの `Sync KONAMI shops / manual` を確認し、DB更新なしの確認が成功していることを確かめる。
7. `installShopSyncTrigger` を実行する。この関数は設定・ワークフローの有効状態を確認し、`tickShopSync` の1分トリガーを1本だけ作る。他の関数のトリガーは削除しない。
8. 07:30を過ぎている場合は `tickShopSync` を手動実行して通常の起動を確認できる。`inspectShopSyncSetup` で送信日と実行ログURLを確認する。
9. このリポジトリの `.github/workflows/sync-konami-shops.yml` はGASへの切り替え後、`workflow_dispatch` のみを持つ。古い設定から移行する場合は、GASの正常な起動を確認してから旧 `schedule` を削除してmainへ反映する。GitHub上のワークフロー全体は無効化しない。
10. 翌朝のGAS実行履歴とGitHub Actionsの実行名 `GAS YYYY-MM-DD 07:30 JST`、開始時刻を照合する。手動確認の成功だけで予約起動も確認済みとは扱わない。

GitHubの[workflow dispatch API](https://docs.github.com/en/rest/actions/workflows#create-a-workflow-dispatch-event)はAPI版によって200または204で受付成功を返す。両方に対応する。DBのURLやSentry DSNをGASへ移す必要はない。

## 無料枠と障害時

無料Googleアカウントの[現行上限](https://developers.google.com/apps-script/guides/services/quotas)はトリガー実行合計90分/日、Properties読み書き5万回/日、URL Fetch2万回/日。通常は毎分の短い時刻確認と、1日1回のAPI依頼だけを行う。

送信失敗はGASの実行履歴、同期処理の失敗はGitHub Actionsで確認する。401・403の場合はPATの期限・対象リポジトリ・Actions権限を確認する。429や5xxの場合はGitHubの状態を確認する。日付の記録を消す前に、その日の実行が受理済みか確認する。

停止するときはGASのトリガー画面で `tickShopSync` のトリガーだけを削除する。必要ならPATも失効させる。

## ローカル検証

```bash
node --test automation/shop-sync/Code.test.cjs
```

実際のGoogle・GitHub APIを呼ばずに、07:30の境界、同日重複防止、失敗時の再試行、トリガーの再設定を検証する。
