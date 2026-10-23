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
let triggerId = 0;

function makeTrigger(handler, schedule = {}) {
  const id = `test-trigger-${++triggerId}`;
  return { ...schedule, getHandlerFunction: () => handler, getUniqueId: () => id };
}

function harness(options = {}) {
  let now = new Date(options.now ?? '2026-10-23T07:00:00+09:00').getTime();
  const properties = new Map(Object.entries({ GITHUB_TOKEN: token, ...(options.properties ?? {}) }));
  const requests = [];
  const logs = [];
  const responses = [...(options.responses ?? [204])];
  const triggers = [...(options.triggers ?? [])];
  const createdTriggers = [];
  const deletedTriggers = [];
  let releases = 0;
  let lockAttempts = 0;
  let creationAttempts = 0;
  let maxTriggerCount = triggers.length;
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
          at(date) { this.schedule = { ...this.schedule, at: Number(date) }; return this; },
          after(milliseconds) { this.schedule = { ...this.schedule, at: now + milliseconds }; return this; },
          atHour(hour) { this.schedule = { ...this.schedule, hour }; return this; },
          everyDays(days) { this.schedule = { ...this.schedule, days }; return this; },
          inTimezone(timeZone) { this.schedule = { ...this.schedule, timeZone }; return this; },
          everyMinutes() { assert.fail('All-day minute triggers must not be installed'); },
          create() {
            creationAttempts += 1;
            if (options.failCreationNumbers?.includes(creationAttempts)) throw new Error('Trigger creation failed');
            if (triggers.length >= 20) throw new Error('Trigger quota exceeded');
            const trigger = makeTrigger(handler, this.schedule);
            createdTriggers.push(trigger);
            triggers.push(trigger);
            maxTriggerCount = Math.max(maxTriggerCount, triggers.length);
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
    get maxTriggerCount() { return maxTriggerCount; },
    forHandler(handler) { return triggers.filter((trigger) => trigger.getHandlerFunction() === handler); },
    setNow(value) { now = new Date(value).getTime(); },
  };
}

function assertNextMorning(app, iso = '2026-10-23T22:00:00.000Z') {
  const next = app.forHandler('tickShopSync');
  assert.equal(next.length, 1);
  assert.equal(new Date(next[0].at).toISOString(), iso);
}

test('normal handlers never call GitHub before 07:00 or from 08:00 JST onward', () => {
  for (const time of ['04:59:59', '05:00:00', '06:59:59', '08:00:00', '09:00:00']) {
    const app = harness({ now: `2026-10-23T${time}+09:00` });

    assert.equal(app.context.tickShopSync().status, 'outside_window', time);
    assert.equal(app.context.dailyShopSyncFallback().status, 'outside_window', time);
    assert.equal(app.context.retryShopSync().status, 'outside_window', time);
    assert.equal(app.requests.length, 0, time);
    assert.equal(app.properties.has(lastDayKey), false, time);
    assert.equal(app.forHandler('retryShopSync').length, 0, time);
  }
});

for (const code of [200, 204]) {
  test(`07:00 JST dispatches main, schedules the next morning, and accepts HTTP ${code}`, () => {
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
    assertNextMorning(app);
    assert.equal(app.releases, 1);
    assert.doesNotMatch(JSON.stringify(result) + app.logs.join('\n'), new RegExp(token));
    if (code === 200) {
      assert.equal(result.runUrl, 'https://github.com/misato729/score-manager-backend/actions/runs/123');
    }
  });
}

test('the independent fallback does not dispatch a second run after one-shot success', () => {
  const app = harness();
  app.context.tickShopSync();
  app.setNow('2026-10-23T07:40:00+09:00');

  assert.equal(app.context.dailyShopSyncFallback().status, 'already_dispatched');
  assert.equal(app.requests.length, 1);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
  assertNextMorning(app);
});

test('the following JST day can dispatch once again', () => {
  const app = harness();
  app.context.tickShopSync();
  app.setNow('2026-10-24T07:00:00+09:00');

  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 2);
  assert.equal(app.properties.get(lastDayKey), '2026-10-24');
  assert.equal(JSON.parse(app.requests[1].payload).inputs.scheduled_on, '2026-10-24');
  assertNextMorning(app, '2026-10-24T22:00:00.000Z');
});

test('a delayed 07:59 tick catches up within the permitted hour', () => {
  const app = harness({ now: '2026-10-23T07:59:59+09:00' });

  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 1);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
});

test('HTTP 500 schedules one retry a minute later and a successful retry clears it', () => {
  const app = harness({ responses: [{ code: 500, body: `private response ${token}` }, 204] });

  assert.throws(() => app.context.tickShopSync(), (error) => /HTTP 500/.test(error.message) && !String(error).includes(token));
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 1);
  assertNextMorning(app);
  assert.equal(app.forHandler('retryShopSync').length, 1);
  assert.equal(new Date(app.forHandler('retryShopSync')[0].at).toISOString(), '2026-10-22T22:01:00.000Z');
  app.setNow('2026-10-23T07:01:00+09:00');
  assert.equal(app.context.retryShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 2);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
  assert.equal(app.forHandler('retryShopSync').length, 0);
  assert.doesNotMatch(app.logs.join('\n'), new RegExp(token));
});

test('network failures do not leak credentials or prevent a later retry', () => {
  const app = harness({ responses: [new Error(`Network error with Bearer ${token}`), 204] });

  assert.throws(() => app.context.tickShopSync(), (error) => /GitHub API/.test(error.message) && !String(error).includes(token));
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 1);
  app.setNow('2026-10-23T07:01:00+09:00');
  assert.equal(app.context.retryShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 2);
  assert.doesNotMatch(app.logs.join('\n'), new RegExp(token));
});

test('a failure at 07:59 does not schedule a retry in the 08:00 hour', () => {
  const app = harness({ now: '2026-10-23T07:59:00+09:00', responses: [500] });

  assert.throws(() => app.context.tickShopSync(), /HTTP 500/);
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.forHandler('retryShopSync').length, 0);
  assertNextMorning(app);
});

test('a busy lock skips dispatch without releasing another execution\'s lock', () => {
  const app = harness({ busy: true });

  assert.equal(app.context.tickShopSync().status, 'locked');
  assert.equal(app.requests.length, 0);
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 0);
  assert.equal(app.createdTriggers.length, 0);
});

test('a missing GitHub token fails safely before any external request', () => {
  const app = harness({ properties: { GITHUB_TOKEN: null } });

  assert.throws(() => app.context.tickShopSync(), /GITHUB_TOKEN/);
  assert.equal(app.requests.length, 0);
  assert.equal(app.properties.has(lastDayKey), false);
  assert.equal(app.releases, 1);
  assert.equal(app.forHandler('retryShopSync').length, 0);
});

test('a manual dry-run uses dry_run=true and preserves the daily dispatch record', () => {
  const app = harness({
    now: '2026-10-23T09:00:00+09:00',
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
  assert.equal(app.createdTriggers.length, 0);
});

test('manual dry-runs also avoid GitHub during the 05:00–06:59 maintenance window', () => {
  for (const time of ['05:00:00', '06:59:59']) {
    const app = harness({ now: `2026-10-23T${time}+09:00` });

    assert.throws(() => app.context.dispatchShopSyncDryRun());
    assert.equal(app.requests.length, 0);
    assert.equal(app.properties.has(lastDayKey), false);
  }
});

test('reinstallation leaves one next-morning trigger plus a daily fallback and preserves other handlers', () => {
  const other = makeTrigger('anotherAutomation');
  const old = makeTrigger('tickShopSync');
  const retry = makeTrigger('retryShopSync');
  const fallback = makeTrigger('dailyShopSyncFallback');
  const app = harness({ triggers: [other, old, retry, fallback], responses: [activeWorkflow, activeWorkflow] });

  app.context.installShopSyncTrigger();
  app.context.installShopSyncTrigger();

  assert.equal(app.triggers.length, 3);
  assert.equal(app.triggers.includes(other), true);
  assert.equal(app.deletedTriggers.includes(other), false);
  assertNextMorning(app);
  const fallbacks = app.forHandler('dailyShopSyncFallback');
  assert.equal(fallbacks.length, 1);
  assert.equal(fallbacks[0].hour, 7);
  assert.equal(fallbacks[0].days, 1);
  assert.equal(fallbacks[0].timeZone, 'Asia/Tokyo');
  assert.equal(app.forHandler('retryShopSync').length, 0);
});

for (const failureNumber of [1, 2]) {
  test(`installation failure while creating trigger ${failureNumber} preserves all old triggers`, () => {
    const old = makeTrigger('tickShopSync');
    const fallback = makeTrigger('dailyShopSyncFallback');
    const other = makeTrigger('anotherAutomation');
    const app = harness({
      triggers: [old, fallback, other], responses: [activeWorkflow], failCreationNumbers: [failureNumber],
    });

    assert.throws(() => app.context.installShopSyncTrigger());
    assert.deepEqual(app.triggers, [old, fallback, other]);
    assert.equal(app.deletedTriggers.includes(old), false);
    assert.equal(app.deletedTriggers.includes(fallback), false);
    assert.equal(app.deletedTriggers.includes(other), false);
  });
}

test('next-morning scheduling failure does not block today\'s dispatch and the fallback repairs it', () => {
  const old = makeTrigger('tickShopSync');
  const fallback = makeTrigger('dailyShopSyncFallback');
  const app = harness({ triggers: [old, fallback], failCreationNumbers: [1] });

  assert.equal(app.context.tickShopSync().status, 'dispatched');
  assert.equal(app.requests.length, 1);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
  assert.equal(app.triggers.includes(fallback), true);
  app.setNow('2026-10-23T07:40:00+09:00');
  assert.equal(app.context.dailyShopSyncFallback().status, 'already_dispatched');
  assert.equal(app.requests.length, 1);
  assertNextMorning(app);
  assert.equal(app.triggers.includes(fallback), true);
});

test('repeated API failures replace the retry trigger instead of exhausting the trigger quota', () => {
  const fallback = makeTrigger('dailyShopSyncFallback');
  const app = harness({ triggers: [fallback], responses: Array(5).fill(500) });

  for (let minute = 0; minute < 5; minute += 1) {
    app.setNow(`2026-10-23T07:0${minute}:00+09:00`);
    assert.throws(() => app.context.retryShopSync(), /HTTP 500/);
    assert.equal(app.forHandler('retryShopSync').length, 1);
    assert.equal(app.forHandler('tickShopSync').length, 1);
    assert.equal(app.triggers.includes(fallback), true);
  }
  assert.equal(app.requests.length, 5);
  assert.equal(app.properties.has(lastDayKey), false);
  assert.ok(app.maxTriggerCount <= 4);
});

test('next one-shot scheduling uses the JST 07:00 boundary, including the previous UTC date', () => {
  for (const [time, expected] of [
    ['06:59:59', '2026-10-22T22:00:00.000Z'],
    ['07:00:00', '2026-10-23T22:00:00.000Z'],
    ['09:00:00', '2026-10-23T22:00:00.000Z'],
  ]) {
    const app = harness({ now: `2026-10-23T${time}+09:00`, responses: [activeWorkflow] });

    app.context.installShopSyncTrigger();
    assertNextMorning(app, expected);
  }
});

test('an accepted dispatch with an unreadable response is recorded and never dispatched twice', () => {
  const app = harness({ responses: [{ code: 200, body: `invalid JSON ${token}` }] });

  const result = app.context.tickShopSync();
  assert.equal(result.status, 'dispatched');
  assert.equal(result.runUrl, null);
  assert.equal(app.properties.get(lastDayKey), '2026-10-23');
  assert.equal(app.context.dailyShopSyncFallback().status, 'already_dispatched');
  assert.equal(app.requests.length, 1);
  assert.doesNotMatch(JSON.stringify(result) + app.logs.join('\n'), new RegExp(token));
});
