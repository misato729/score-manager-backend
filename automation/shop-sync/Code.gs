const SHOP_SYNC_TIME_ZONE = 'Asia/Tokyo';
const SHOP_SYNC_WORKFLOW_URL = 'https://api.github.com/repos/misato729/score-manager-backend/actions/workflows/sync-konami-shops.yml';
const SHOP_SYNC_RUN_URL = 'https://github.com/misato729/score-manager-backend/actions/runs/';

// 1分ごとに確認し、日本時間07:30以降に当日分を1回だけ起動します。
function tickShopSync() {
  const now = new Date();
  const day = Utilities.formatDate(now, SHOP_SYNC_TIME_ZONE, 'yyyy-MM-dd');
  if (Utilities.formatDate(now, SHOP_SYNC_TIME_ZONE, 'HH:mm') < '07:30') {
    return {status: 'before_time'};
  }
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {status: 'locked'};
  try {
    const properties = PropertiesService.getScriptProperties();
    if (properties.getProperty('SHOP_SYNC_LAST_DISPATCH_DAY') === day) {
      return {status: 'already_dispatched', day: day};
    }
    const response = githubShopSyncRequest_('post', {
      ref: 'main',
      inputs: {dry_run: 'false', test_notification: 'false', scheduled_on: day}
    });
    // 受付成功を先に保存し、レスポンスの解析失敗で二重起動しないようにします。
    properties.setProperty('SHOP_SYNC_LAST_DISPATCH_DAY', day);
    properties.deleteProperty('SHOP_SYNC_LAST_RUN_URL');
    const runUrl = shopSyncRunUrl_(response);
    if (runUrl) properties.setProperty('SHOP_SYNC_LAST_RUN_URL', runUrl);
    console.log(runUrl || '店舗同期の起動を受け付けました。');
    return {status: 'dispatched', day: day, runUrl: runUrl};
  } finally {
    lock.releaseLock();
  }
}

// GitHubへの接続を確認してからトリガーを交換します。再実行しても1本です。
function installShopSyncTrigger() {
  const response = githubShopSyncRequest_('get');
  let workflow;
  try {
    workflow = JSON.parse(response.getContentText());
  } catch (_) {
    throw new Error('GitHubのワークフロー情報を確認できませんでした。');
  }
  if (!workflow || workflow.state !== 'active') {
    throw new Error('GitHubの店舗同期ワークフローが有効ではありません。');
  }
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) throw new Error('別の設定処理が実行中です。再実行してください。');
  try {
    const previous = ScriptApp.getProjectTriggers()
      .filter(function (trigger) { return trigger.getHandlerFunction() === 'tickShopSync'; });
    // 作成に失敗した場合は既存トリガーを残します。他の関数のトリガーは触りません。
    ScriptApp.newTrigger('tickShopSync').timeBased().everyMinutes(1).create();
    previous.forEach(function (trigger) { ScriptApp.deleteTrigger(trigger); });
    console.log('店舗同期: Asia/Tokyo 07:30以降、1分ごとに当日分の起動を確認します。');
    return {status: 'installed'};
  } finally {
    lock.releaseLock();
  }
}

// 接続テスト専用です。店舗や当日分の起動記録は変更しません。
function dispatchShopSyncDryRun() {
  const response = githubShopSyncRequest_('post', {
    ref: 'main', inputs: {dry_run: 'true', test_notification: 'false'}
  });
  const runUrl = shopSyncRunUrl_(response);
  console.log(runUrl || '店舗同期のdry-runを受け付けました。');
  return {status: 'dry_run_dispatched', runUrl: runUrl};
}

// 認証情報を含めず、現在の起動記録を確認します。
function inspectShopSyncSetup() {
  const properties = PropertiesService.getScriptProperties();
  const day = properties.getProperty('SHOP_SYNC_LAST_DISPATCH_DAY') || '';
  const runUrl = properties.getProperty('SHOP_SYNC_LAST_RUN_URL') || '';
  const state = {
    timeZone: SHOP_SYNC_TIME_ZONE,
    lastDispatchDay: /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : null,
    lastRunUrl: /^https:\/\/github\.com\/misato729\/score-manager-backend\/actions\/runs\/\d+$/.test(runUrl) ? runUrl : null
  };
  console.log(JSON.stringify(state));
  return state;
}

function githubShopSyncRequest_(method, payload) {
  const token = (PropertiesService.getScriptProperties().getProperty('GITHUB_TOKEN') || '').trim();
  if (!token) throw new Error('スクリプトプロパティ GITHUB_TOKEN を設定してください。');
  const options = {
    method: method,
    headers: {
      Accept: 'application/vnd.github+json',
      Authorization: 'Bearer ' + token,
      'X-GitHub-Api-Version': '2026-03-10'
    },
    muteHttpExceptions: true
  };
  if (payload) {
    options.contentType = 'application/json';
    options.payload = JSON.stringify(payload);
  }
  let response;
  try {
    response = UrlFetchApp.fetch(SHOP_SYNC_WORKFLOW_URL + (method === 'post' ? '/dispatches' : ''), options);
  } catch (_) {
    throw new Error('GitHub APIへの接続に失敗しました。次回再試行します。');
  }
  const status = response.getResponseCode();
  if (status < 200 || status >= 300) throw new Error('GitHub API HTTP ' + status);
  return response;
}

function shopSyncRunUrl_(response) {
  try {
    const id = String(JSON.parse(response.getContentText()).workflow_run_id || '');
    return /^\d+$/.test(id) ? SHOP_SYNC_RUN_URL + id : null;
  } catch (_) {
    return null;
  }
}
