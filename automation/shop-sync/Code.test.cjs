const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, 'Code.gs'), 'utf8');
const token = 'private-test-token-must-never-be-logged';
const lastDayKey = 'SHOP_SYNC_LAST_DISPATCH_DAY';
const dispatchUrl = 'https://api.github.com/repos/misato729/score-manager-backend/actions/workflows/sync-konami-shops.yml/dispatches';
const activeWorkflow = { code: 200, body: JSON.stringify({ state: 'active' }) };

function harness(options = {}) {
  let now = new Date(options.now ?? '2026-10-23T07:30:00+09:00').getTime();
  const properties = new Map(Object.entries({ GITHUB_TOKEN: token, ...(options.properties ?? {}) }));
  const requests = [];
  const logs = [];
  const responses = [...(options.responses ?? [204])];
  const triggers = [...(options.triggers ?? [])];
  const createdTriggers = [];
  const deletedTriggers = [];
  let releases = 0;
  let lockAttempts = 0;
  const scriptProperties = {
    getProperty: (key) => properties.get(key) ?? null,
    getProperties: () => Object.fromEntries(properties),
    setProperty(key, value) {
      properties.set(key, String(value));
      return this;
    },
    setProperties(values) {
      for (const [key, value] of Object.entries(values)) this.setProperty(key, value);
      return this;
    },
    deleteProperty(key) {
      properties.delete(key);
      return this;
    },
  };
  const writeLog = (...values) => logs.push(values.map(String).join(' '));
  const context = vm.createContext({
    Date: class extends Date {
      constructor(...args) { super(...(args.length ? args : [now])); }
      static now() { return now; }
    },
    PropertiesService: { getScriptProperties: () => scriptProperties },
    LockService: {
      getScriptLock: () => ({
        tryLock: () => { lockAttempts += 1; return !options.busy; },
        releaseLock: () => { releases += 1; },
      }),
    },
    Utilities: {
      formatDate(date, timeZone, format) {
        const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
          timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
          hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
        }).formatToParts(date).map(({ type, value }) => [type, value]));
        return format.replace(/yyyy|MM|dd|HH|mm|ss/g, (part) => ({
          yyyy: parts.year, MM: parts.month, dd: parts.day,
          HH: parts.hour, mm: parts.minute, ss: parts.second,
        })[part]);
      },
    },
    UrlFetchApp: {
      fetch(url, request) {
        requests.push({ url, ...JSON.parse(JSON.stringify(request)) });
        const response = responses.shift() ?? 204;
        if (response instanceof Error) throw response;
        const code = typeof response === 'number' ? response : response.code;
        return {
          getResponseCode: () => code,
          getContentText: () => typeof response === 'number' ? '' : (response.body ?? ''),
          getHeaders: () => typeof response === 'number' ? {} : (response.headers ?? {}),
          getAllHeaders: () => typeof response === 'number' ? {} : (response.headers ?? {}),
        };
      },
    },
    ScriptApp: {
      getProjectTriggers: () => [...triggers],
      deleteTrigger(trigger) {
        deletedTriggers.push(trigger);
        const index = triggers.indexOf(trigger);
        if (index !== -1) triggers.splice(index, 1);
      },
      newTrigger(handler) {
        const builder = {
          timeBased() { return this; },
          everyMinutes(minutes) { this.minutes = minutes; return this; },
          create() {
            if (options.triggerCreationFailure) throw new Error('Trigger creation failed');
            const trigger = { getHandlerFunction: () => handler, minutes: this.minutes };
            createdTriggers.push(trigger);
            triggers.push(trigger);
            return trigger;
          },
        };
        return builder;
      },
    },
    Logger: { log: writeLog },
    console: { log: writeLog, info: writeLog, warn: writeLog, error: writeLog },
  });
  vm.runInContext(source, context, { filename: 'Code.gs' });
  return {
    context, properties, requests, logs, triggers, createdTriggers, deletedTriggers,
    get releases() { return releases; },
    get lockAttempts() { return lockAttempts; },
    setNow(value) { now = new Date(value).getTime(); },
  };
}

test('07:29 JST does not dispatch or record a completed day', () => {
  const app = harness({ now: '2026-10-23T07:29:59+09:00' });

  assert.equal(app.context.tickShopSync().status, 'before_time');
  assert.equal(app.requests.length, 0);
  assert.equal(app.properties.has(lastDayKey), false);
});

for (const code of [200, 204]) {
  test(`07:30 JST dispatches main with explicit daily inputs and accepts HTTP ${code}`, () => {
    const app = harness({ responses: [{
      code,
      body: code === 200 ? JSON.stringify({
        workflow_run_id: 123,
        html_url: `https://untrusted.example/${token}`,
      }) : '',
    }] });

    const result = app.context.tickShopSync();

    assert.equal(result.status, 'dispatched');
    assert.equal(app.requests.length, 1);
    const request = app.requests[0];
    assert.equal(request.url, dispatchUrl);
    assert.equal(request.method.toLowerCase(), 'post');
    assert.equal(request.contentType, 'application/json');
    assert.equal(request.headers.Authorization, `Bearer ${token}`);
    assert.equal(request.muteHttpExceptions, true);
    const payload = JSON.parse(request.payload);
    assert.equal(payload.ref, 'main');
    assert.equal(String(payload.inputs.dry_run), 'false');
    assert.equal(String(payload.inputs.test_notification), 'false');
    assert.equal(payload.inputs.scheduled_on, '2026-10-23');
    assert.equal(app.properties.get(lastDayKey), '2026-10-23');
    assert.equal(app.releases, 1);
    assert.doesNotMatch(JSON.stringify(result) + app.logs.join('\n'), new RegExp(token));
    if (code === 200) {
      assert.equal(result.runUrl, 'https://github.com/misato729/score-manager-backend/actions/runs/123');
    }
  });
}

test('a second tick on the same JST day does not dispatch again', () => {
  const app = harness();
  app.context.tickShopSync();
  app.setNow('2026-10-23T23:59:00+09:00');

  assert.equal(app.context.tickShopSync().status, 'already_dispatched');
  assert.equal(app.requests.length, 1);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
});

test('the following JST day can dispatch once again', () => {
  const app = harness();
  app.context.tickShopSync();
  app.setNow('2026-10-24T07:30:00+09:00');

  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 2);
  assert.equal(app.properties.get(lastDayKey), '2026-10-24');
  assert.equal(JSON.parse(app.requests[1].payload).inputs.scheduled_on, '2026-10-24');
});

test('a delayed 09:00 tick catches up instead of requiring an exact minute match', () => {
  const app = harness({ now: '2026-10-23T09:00:00+09:00' });

  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 1);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
});

test('HTTP 500 does not mark the day complete and a later tick can retry', () => {
  const app = harness({ responses: [{ code: 500, body: `private response ${token}` }, 204] });

  assert.throws(() => app.context.tickShopSync(), (error) => /HTTP 500/.test(error.message) && !String(error).includes(token));
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 1);
  app.setNow('2026-10-23T09:00:00+09:00');
  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 2);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
  assert.doesNotMatch(app.logs.join('\n'), new RegExp(token));
});

test('network failures do not leak credentials or prevent a later retry', () => {
  const app = harness({ responses: [new Error(`Network error with Bearer ${token}`), 204] });

  assert.throws(() => app.context.tickShopSync(), (error) => /GitHub API/.test(error.message) && !String(error).includes(token));
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 1);
  app.setNow('2026-10-23T09:00:00+09:00');
  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 2);
  assert.doesNotMatch(app.logs.join('\n'), new RegExp(token));
});

test('a busy lock skips dispatch without releasing another execution\'s lock', () => {
  const app = harness({ busy: true });

  assert.equal(app.context.tickShopSync().status, 'locked');
  assert.equal(app.requests.length, 0);
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 0);
});

test('a missing GitHub token fails safely before any external request', () => {
  const app = harness({ properties: { GITHUB_TOKEN: null } });

  assert.throws(() => app.context.tickShopSync(), /GITHUB_TOKEN/);
  assert.equal(app.requests.length, 0);
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 1);
});

test('a manual dry-run uses dry_run=true and preserves the daily dispatch record', () => {
  const app = harness({
    now: '2026-10-23T06:00:00+09:00',
    properties: { [lastDayKey]: '2026-10-22', SHOP_SYNC_LAST_RUN_URL: 'https://github.com/example/previous' },
  });

  app.context.dispatchShopSyncDryRun();

  assert.equal(app.requests.length, 1);
  assert.equal(app.requests[0].url, dispatchUrl);
  const payload = JSON.parse(app.requests[0].payload);
  assert.equal(payload.ref, 'main');
  assert.equal(String(payload.inputs.dry_run), 'true');
  assert.equal(String(payload.inputs.test_notification), 'false');
  assert.ok(!payload.inputs.scheduled_on);
  assert.equal(app.properties.get(lastDayKey), '2026-10-22');
  assert.equal(app.properties.get('SHOP_SYNC_LAST_RUN_URL'), 'https://github.com/example/previous');
});

test('reinstalling the minute trigger leaves one sync handler and preserves other handlers', () => {
  const other = { getHandlerFunction: () => 'anotherAutomation' };
  const old = { getHandlerFunction: () => 'tickShopSync' };
  const duplicate = { getHandlerFunction: () => 'tickShopSync' };
  const app = harness({ triggers: [other, old, duplicate], responses: [activeWorkflow, activeWorkflow] });

  app.context.installShopSyncTrigger();
  app.context.installShopSyncTrigger();

  assert.equal(app.triggers.length, 2);
  assert.equal(app.triggers.includes(other), true);
  assert.equal(app.deletedTriggers.includes(other), false);
  const syncTriggers = app.triggers.filter((trigger) => trigger.getHandlerFunction() === 'tickShopSync');
  assert.equal(syncTriggers.length, 1);
  assert.equal(syncTriggers[0].minutes, 1);
});

test('a failed replacement trigger creation preserves the existing trigger', () => {
  const old = { getHandlerFunction: () => 'tickShopSync' };
  const other = { getHandlerFunction: () => 'anotherAutomation' };
  const app = harness({
    triggers: [old, other], responses: [activeWorkflow], triggerCreationFailure: true,
  });

  assert.throws(() => app.context.installShopSyncTrigger());
  assert.deepEqual(app.triggers, [old, other]);
  assert.equal(app.deletedTriggers.length, 0);
});
