const SHOP_SYNC_TIME_ZONE = 'Asia/Tokyo';
const SHOP_SYNC_WORKFLOW_URL = 'https://api.github.com/repos/misato729/score-manager-backend/actions/workflows/sync-konami-shops.yml';
const SHOP_SYNC_RUN_URL = 'https://github.com/misato729/score-manager-backend/actions/runs/';

// 単発予約を翌朝へつなぎます。毎分トリガーは使用しません。
function tickShopSync() {
  return runShopSync_();
}

// 単発予約が欠落しても、独立した日次トリガーが7時台に回復させます。
function dailyShopSyncFallback() {
  return runShopSync_();
}

// GitHubへの起動要求が失敗した場合だけ使う単発トリガーです。
function retryShopSync() {
  return runShopSync_();
}

function runShopSync_() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {status: 'locked'};
  try {
    try {
      replaceShopSyncOneShot_('tickShopSync', nextShopSyncTime_(new Date()));
    } catch (_) {
      // 日次fallbackを残し、本日分の起動は続行します。
      console.log('次回の単発予約に失敗しました。日次トリガーで再確認します。');
    }
    // 予約操作中に08:00をまたぐ可能性があるため、判定用の時刻は取り直します。
    const now = new Date();
    const day = Utilities.formatDate(now, SHOP_SYNC_TIME_ZONE, 'yyyy-MM-dd');
    const time = Utilities.formatDate(now, SHOP_SYNC_TIME_ZONE, 'HH:mm');
    if (time < '07:00' || time >= '08:00') {
      deleteShopSyncTriggers_(['retryShopSync']);
      return {status: 'outside_window'};
    }
    const properties = PropertiesService.getScriptProperties();
    if (properties.getProperty('SHOP_SYNC_LAST_DISPATCH_DAY') === day) {
      deleteShopSyncTriggers_(['retryShopSync']);
      return {status: 'already_dispatched', day: day};
    }
    shopSyncToken_();
    let response;
    try {
      response = githubShopSyncRequest_('post', {
        ref: 'main',
        inputs: {dry_run: 'false', test_notification: 'false', scheduled_on: day}
      });
    } catch (error) {
      scheduleShopSyncRetry_(new Date());
      throw error;
    }
    if (!response) {
      deleteShopSyncTriggers_(['retryShopSync']);
      return {status: 'outside_window'};
    }
    // 受付成功を先に保存し、レスポンスの解析失敗で二重起動しないようにします。
    properties.setProperty('SHOP_SYNC_LAST_DISPATCH_DAY', day);
    properties.deleteProperty('SHOP_SYNC_LAST_RUN_URL');
    const runUrl = shopSyncRunUrl_(response);
    if (runUrl) properties.setProperty('SHOP_SYNC_LAST_RUN_URL', runUrl);
    deleteShopSyncTriggers_(['retryShopSync']);
    console.log(runUrl || '店舗同期の起動を受け付けました。');
    return {status: 'dispatched', day: day, runUrl: runUrl};
  } finally {
    lock.releaseLock();
  }
}

// 単発予約と独立した日次fallbackの2本を設定します。
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
    const next = nextShopSyncTime_(new Date());
    const previous = ScriptApp.getProjectTriggers()
      .filter(function (trigger) { return isShopSyncHandler_(trigger.getHandlerFunction()); });
    const created = [];
    try {
      created.push(ScriptApp.newTrigger('tickShopSync').timeBased().at(next).create());
      created.push(ScriptApp.newTrigger('dailyShopSyncFallback').timeBased()
        .atHour(7).everyDays(1).inTimezone(SHOP_SYNC_TIME_ZONE).create());
    } catch (_) {
      created.forEach(function (trigger) { ScriptApp.deleteTrigger(trigger); });
      throw new Error('トリガーを作成できませんでした。既存の設定は維持しています。');
    }
    // 新しい2本の作成が成功してから、既存の店舗同期トリガーだけを削除します。
    previous.forEach(function (trigger) { ScriptApp.deleteTrigger(trigger); });
    PropertiesService.getScriptProperties().setProperty('SHOP_SYNC_NEXT_TRIGGER_AT', next.toISOString());
    console.log('店舗同期: Asia/Tokyo 07:00の単発予約と7時台の日次fallbackを設定しました。');
    return {status: 'installed', nextTriggerAt: next.toISOString()};
  } finally {
    lock.releaseLock();
  }
}

// 接続テスト専用です。店舗や当日分の起動記録は変更しません。
function dispatchShopSyncDryRun() {
  const time = Utilities.formatDate(new Date(), SHOP_SYNC_TIME_ZONE, 'HH:mm');
  if (time >= '05:00' && time < '07:00') {
    throw new Error('日本時間05:00〜07:00は公式検索のメンテナンス時間のため実行しません。');
  }
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
  const next = properties.getProperty('SHOP_SYNC_NEXT_TRIGGER_AT') || '';
  const state = {
    timeZone: SHOP_SYNC_TIME_ZONE,
    lastDispatchDay: /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : null,
    lastRunUrl: /^https:\/\/github\.com\/misato729\/score-manager-backend\/actions\/runs\/\d+$/.test(runUrl) ? runUrl : null,
    nextTriggerAt: /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/.test(next) ? next : null
  };
  console.log(JSON.stringify(state));
  return state;
}

function nextShopSyncTime_(now) {
  const day = Utilities.formatDate(now, SHOP_SYNC_TIME_ZONE, 'yyyy-MM-dd');
  const next = new Date(day + 'T07:00:00+09:00');
  return next.getTime() > now.getTime() ? next : new Date(next.getTime() + 86400000);
}

function replaceShopSyncOneShot_(handler, when) {
  const previous = ScriptApp.getProjectTriggers()
    .filter(function (trigger) { return trigger.getHandlerFunction() === handler; });
  ScriptApp.newTrigger(handler).timeBased().at(when).create();
  previous.forEach(function (trigger) { ScriptApp.deleteTrigger(trigger); });
  if (handler === 'tickShopSync') {
    PropertiesService.getScriptProperties().setProperty('SHOP_SYNC_NEXT_TRIGGER_AT', when.toISOString());
  }
}

function scheduleShopSyncRetry_(now) {
  const retry = new Date(now.getTime() + 60000);
  if (Utilities.formatDate(retry, SHOP_SYNC_TIME_ZONE, 'HH:mm').slice(0, 2) !== '07') {
    deleteShopSyncTriggers_(['retryShopSync']);
    return;
  }
  try {
    replaceShopSyncOneShot_('retryShopSync', retry);
  } catch (_) {
    console.log('再試行の単発予約に失敗しました。日次トリガーで再確認します。');
  }
}

function deleteShopSyncTriggers_(handlers) {
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (handlers.indexOf(trigger.getHandlerFunction()) !== -1) ScriptApp.deleteTrigger(trigger);
  });
}

function isShopSyncHandler_(handler) {
  return ['tickShopSync', 'dailyShopSyncFallback', 'retryShopSync'].indexOf(handler) !== -1;
}

function githubShopSyncRequest_(method, payload) {
  const token = shopSyncToken_();
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
  // 認証情報の読み出し等でも時刻が進むため、POSTは送信直前にも確認します。
  if (method === 'post' && payload && payload.inputs) {
    const time = Utilities.formatDate(new Date(), SHOP_SYNC_TIME_ZONE, 'HH:mm');
    if (payload.inputs.dry_run === 'false' && (time < '07:00' || time >= '08:00')) return null;
    if (payload.inputs.dry_run === 'true' && time >= '05:00' && time < '07:00') {
      throw new Error('日本時間05:00〜07:00は公式検索のメンテナンス時間のため実行しません。');
    }
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

function shopSyncToken_() {
  const token = (PropertiesService.getScriptProperties().getProperty('GITHUB_TOKEN') || '').trim();
  if (!token) throw new Error('スクリプトプロパティ GITHUB_TOKEN を設定してください。');
  return token;
}

function shopSyncRunUrl_(response) {
  try {
    const id = String(JSON.parse(response.getContentText()).workflow_run_id || '');
    return /^\d+$/.test(id) ? SHOP_SYNC_RUN_URL + id : null;
  } catch (_) {
    return null;
  }
}
