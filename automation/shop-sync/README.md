# GASによる毎朝07:00の店舗同期

Google Apps Scriptの無料枠から、既存のGitHub Actionsを起動する。同期・5日連続の不在判定・復帰時の再公開・Sentry通知は引き続きGitHub Actions内で実行する。

## 起動方法

- `tickShopSync` は日本時間07:00を指定する単発予約で起動し、次の07:00を予約する。全日の毎分トリガーは作らない。
- `dailyShopSyncFallback` は独立した日次トリガーとして7時台に1回動き、単発予約の欠落を回復する。正常時は当日送信済みなのでGitHub APIを呼ばない。通常のGAS起動はこの2回だけ。
- `07:00 <= 日本時間 < 08:00` の場合だけ通常の起動依頼を送る。KONAMIのメンテナンス時間05:00〜07:00を含む時間帯の外では依頼せず、次の07:00の予約だけを残す。7時台全体を超える遅延の場合、その日の通常起動は見送る。
- スクリプトロックと当日送信記録で、同時起動や予備確認による重複送信を防ぐ。
- GitHubが依頼を受理した後にだけ送信済みを記録する。起動依頼のAPIエラー時に限り、1分以降の単発再試行 `retryShopSync` を7時台だけ予約する。8時以降になる再試行は作らない。
- 「送信済み」はGitHubの受付成功を意味する。同期の正常終了はGitHub Actionsの結果で確認する。受付後に同期処理が失敗した場合は、自動で毎分再送せず、原因を確認して手動実行する。
- 通信切断などで受付応答を受け取れなかった場合は重複送信の可能性がある。同期本体は同日に複数回実行しても不在日数を増やさない。

GASの[日次トリガーは時刻が揺れる](https://developers.google.com/apps-script/guides/triggers/installable#time-driven_triggers)ため、通常起動は[`at(Date)` で次の07:00を指定](https://developers.google.com/apps-script/reference/script/clock-trigger-builder#at(Date))する。日次トリガーの時刻の揺れは7時台の予備確認だけに使う。単発予約にも厳密な開始時刻の保証はなく、GitHub runnerの待ち時間も加わる。

## 初回設定

1. [Apps Script](https://script.google.com/home)で、本人だけが編集できる独立したプロジェクト `score-manager-shop-sync` を作成する。
2. `Code.gs` にこのディレクトリの同名ファイルを貼り付ける。プロジェクトの設定でマニフェスト表示を有効にし、`appsscript.json` も同名ファイルの内容に合わせる。タイムゾーンは `Asia/Tokyo`。
3. GitHubで[Fine-grained personal access token](https://github.com/settings/personal-access-tokens/new)を作る。対象リポジトリは **misato729/score-manager-backendだけ**、Repository permissionsの **Actions: Read and write** を許可する。他の追加権限は不要。期限を設定し、期限切れ前に更新する。
4. GASの **プロジェクトの設定 → スクリプト プロパティ** に、キー `GITHUB_TOKEN` とトークンを登録する。トークンをソース・Git・ログ・チャットへ記載しない。プロジェクト編集者はプロパティを読めるため、共有しない。
5. GASエディタから `dispatchShopSyncDryRun` を実行する。初回にGoogleの権限確認が表示される。必要なのは外部APIへの接続と、このスクリプトのトリガー管理だけ。Gmail・Drive全体・DBへの権限は要求しない。
6. GitHub Actionsの `Sync KONAMI shops / manual` を確認し、DB更新なしの確認が成功していることを確かめる。
7. `installShopSyncTrigger` を実行する。この関数は設定・ワークフローの有効状態を確認し、次の07:00の単発予約と7時台の予備確認の2本を作る。両方の作成に成功してから、旧構成の毎分トリガーを含む店舗同期用の予約を交換する。他の関数のトリガーは削除しない。
8. 7時台は `tickShopSync` を手動実行して通常の起動を確認できる。それ以外の時間帯は通常の起動依頼を送らない。`inspectShopSyncSetup` で送信日・実行ログURL・次の予約時刻を確認する。GASの `dispatchShopSyncDryRun` も05:00〜07:00は実行しない。
9. このリポジトリの `.github/workflows/sync-konami-shops.yml` はGASへの切り替え後、`workflow_dispatch` のみを持つ。古い設定から移行する場合は、GASの正常な起動を確認してから旧 `schedule` を削除してmainへ反映する。GitHub上のワークフロー全体は無効化しない。
10. 翌朝のGAS実行履歴とGitHub Actionsの実行名 `GAS YYYY-MM-DD 07:00 JST`、開始時刻を照合する。手動確認の成功だけで予約起動も確認済みとは扱わない。

GitHubの[workflow dispatch API](https://docs.github.com/en/rest/actions/workflows#create-a-workflow-dispatch-event)はAPI版によって200または204で受付成功を返す。両方に対応する。DBのURLやSentry DSNをGASへ移す必要はない。

## 無料枠と障害時

無料Googleアカウントの[現行上限](https://developers.google.com/apps-script/guides/services/quotas)はトリガー実行合計90分/日、Properties読み書き5万回/日、URL Fetch2万回/日。通常はGASの起動2回と、1日1回のAPI依頼だけを行う。使用済みの単発予約を整理し、予約本数が増え続けないようにする。

送信失敗はGASの実行履歴、同期処理の失敗はGitHub Actionsで確認する。401・403の場合はPATの期限・対象リポジトリ・Actions権限を確認する。429や5xxの場合はGitHubの状態を確認する。日付の記録を消す前に、その日の実行が受理済みか確認する。

停止するときはGASのトリガー画面で `tickShopSync`・`dailyShopSyncFallback`・`retryShopSync` の予約を削除する。予備確認だけを残すと予約を復旧するため、店舗同期用の予約はまとめて停止する。必要ならPATも失効させる。

## ローカル検証

```bash
node --test automation/shop-sync/Code.test.cjs
```

実際のGoogle・GitHub APIを呼ばずに、07:00と08:00の境界、保守時間の抑止、同日重複防止、失敗時だけの再試行、翌日の予約と予備確認、旧トリガーの交換を検証する。
