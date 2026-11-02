// End-to-end smoke tests for the ccenter module against a running stack (see tests/setup.sh).
//   NODE_PATH=$(npm root -g) BASE_URL=http://localhost:8083 COMPOSE_PROJECT=ccenter-83 node --test smoke.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const h = require('./helpers');
const { updateModule, clickAndSettle, eventually } = h;

const MOD = h.cfg.base + '/modules/ccenter';
const ERR_TEXT = /This page cannot be displayed due to an internal error|Fatal error|Parse error|Warning:|Deprecated:|Notice:/;

let browser;
let admin;       // admin browser context (logged in)
let guest;       // anonymous browser context
const state = {};

async function bodyText(page) {
  return (await page.innerText('body')).replace(/\s+/g, ' ');
}
async function assertOk(page, label) {
  const t = await bodyText(page);
  assert.ok(!ERR_TEXT.test(t), `${label}: error text on page: ${t.slice(0, 300)}`);
}


// Submit the sample form as an anonymous visitor in a fresh session; returns { msgId, onepass }.
async function submitSample(name) {
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  await p.goto(`${MOD}/index.php?form=${state.formId}`);
  await p.fill('[name=cc1]', name);
  await p.fill('[name=cc2]', 'visitor@example.com');
  await p.fill('[name=cc2_conf]', 'visitor@example.com');
  await p.check('[name=cc3][value="Others"]');
  await p.fill('[name=cc4]', 'second message');
  await clickAndSettle(p, 'input[type=submit][value=Confirm]');
  await Promise.all([p.waitForURL(/message\.php\?id=\d+/, { timeout: 15000 }),
    p.click('form[name=order] input[type=submit]:not([name=edit])')]).catch(async (e) => {
    throw new Error(`submitSample did not reach the message page (${p.url()}): ${(await bodyText(p)).slice(0, 500)}`);
  });
  const u = new URL(p.url());
  await ctx.close();
  return { msgId: u.searchParams.get('id'), onepass: u.searchParams.get('p') };
}

// ImpressCMS keeps sessions in the database without locking, so a forgotten tab that still reloads or
// pings in the background can overwrite the session (and the form tokens in it) of the tab that is
// being tested. Every page opened by a test is therefore closed when the test ends.
const openPages = [];
function trackPages(ctx) {
  const open = ctx.newPage.bind(ctx);
  ctx.newPage = async () => {
    const page = await open();
    openPages.push(page);
    return page;
  };
  return ctx;
}
test.afterEach(async () => {
  while (openPages.length) await openPages.pop().close().catch(() => {});
});

test.before(async () => {
  browser = await chromium.launch();
  admin = trackPages(await browser.newContext());
  guest = trackPages(await browser.newContext());
  // keep the suite repeatable: only the sample form (id of the installer) survives
  h.sql("DELETE FROM icms_ccenter_form WHERE title<>'Contact us'; TRUNCATE icms_ccenter_message; TRUNCATE icms_ccenter_log; TRUNCATE icms_protector_access; DELETE FROM icms_xoopscomments WHERE com_modid=(SELECT mid FROM icms_modules WHERE dirname='ccenter')");
  const loginPage = await admin.newPage();
  await h.login(loginPage);
  await loginPage.close();
  h.clearPhpLog();
});
test.after(async () => { await browser.close(); });

test('module is installed with the sample form', () => {
  assert.equal(h.sql("SELECT count(*) FROM icms_modules WHERE dirname='ccenter'"), '1');
  assert.equal(h.sql("SELECT count(*) FROM icms_ccenter_form WHERE title='Contact us'"), '1');
  state.formId = h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Contact us'");
});

test('module update runs cleanly and can be repeated', async () => {
  const p = await admin.newPage();
  for (let i = 0; i < 2; i++) {
    const t = await updateModule(p);
    assert.ok(!ERR_TEXT.test(t), `update run ${i + 1}: ${t.slice(0, 400)}`);
    assert.match(t, /ccenter/i);
  }
  assert.equal(h.sql("SELECT count(*) FROM icms_modules WHERE dirname='ccenter'"), '1');
});

test('anonymous visitor sees the form list', async () => {
  const p = await guest.newPage();
  await p.goto(`${MOD}/index.php`);
  await assertOk(p, 'index');
  // a single permitted form is shown directly
  assert.match(await bodyText(p), /Your name/);
});

test('anonymous visitor submits the sample form (confirm, then store)', async () => {
  const p = await guest.newPage();
  await p.goto(`${MOD}/index.php?form=${state.formId}`);
  await p.fill('[name=cc1]', 'Test Visitor');
  await p.fill('[name=cc2]', 'visitor@example.com');
  await p.fill('[name=cc2_conf]', 'visitor@example.com');
  await p.check('[name=cc3][value="Query about us"]');
  await p.fill('[name=cc4]', 'Hello ccenter\nsecond line');
  await clickAndSettle(p, 'input[type=submit][value=Confirm]');
  await assertOk(p, 'confirm page');
  const store = await p.$('form[name=order] input[type=submit]:not([name=edit])');
  assert.ok(store, 'confirm page offers a send button');
  await Promise.all([p.waitForURL(/message\.php\?id=\d+/, { timeout: 15000 }), store.click()]);
  state.msgUrl = p.url();
  state.msgId = new URL(p.url()).searchParams.get('id');
  await assertOk(p, 'message page');
  assert.match(await bodyText(p), /Test Visitor/);
  assert.equal(h.sql(`SELECT status FROM icms_ccenter_message WHERE msgid=${state.msgId}`), '-');
  assert.match(h.sql(`SELECT body FROM icms_ccenter_message WHERE msgid=${state.msgId}`), /Test Visitor/);
  state.onepass = new URL(p.url()).searchParams.get('p');
  assert.ok(state.onepass, 'guest gets a one-time password in the URL');
});

test('validation rejects an empty required field', async () => {
  const p = await guest.newPage();
  await p.goto(`${MOD}/index.php?form=${state.formId}`);
  await clickAndSettle(p, 'input[type=submit][value=Confirm]');
  assert.match(p.url(), /index\.php/);
  await assertOk(p, 'validation');
});

test('admin pages load without errors (cold admin menu cache)', async () => {
  h.dropAdminMenuCache();   // core rebuilds it during this very request
  const p = await admin.newPage();
  for (const u of ['admin/index.php', `admin/index.php?formid=${state.formId}`, 'admin/index.php?op=edit',
    'admin/msgadm.php', 'admin/help.php', 'admin/about.php', 'index.php', 'list.php', 'charge.php', 'reception.php']) {
    await p.goto(`${MOD}/${u}`);
    await assertOk(p, u);
  }
});

test('admin creates and edits a form', async () => {
  const p = await admin.newPage();
  await p.goto(`${MOD}/admin/index.php?op=edit`);
  await p.fill('[name=title]', 'Smoke form');
  await p.fill('[name=defs]', 'Subject*,size=30\nDetails,textarea');
  await clickAndSettle(p, '[name=formdefs]');
  await assertOk(p, 'after save');
  await eventually(() => h.sql("SELECT count(*) FROM icms_ccenter_form WHERE title='Smoke form'") === '1');
});

test('person in charge opens the message, status becomes accepted, then replied', async () => {
  const p = await admin.newPage();
  await p.goto(`${MOD}/message.php?id=${state.msgId}`);
  await assertOk(p, 'message as admin');
  assert.equal(h.sql(`SELECT status FROM icms_ccenter_message WHERE msgid=${state.msgId}`), 'a');
  await p.selectOption('select[name=status]', 'b');
  await clickAndSettle(p, 'form[action="status.php"] input[type=submit]');
  await p.waitForURL(/message\.php/, { timeout: 15000 });
  await eventually(() => h.sql(`SELECT status FROM icms_ccenter_message WHERE msgid=${state.msgId}`) === 'b');
  await eventually(() => Number(h.sql(`SELECT count(*) FROM icms_ccenter_log WHERE midref=${state.msgId}`)) >= 1);   // status changes are logged
});

test('message appears in the staff lists and message admin', async () => {
  const p = await admin.newPage();
  // charge.php lists open messages by default; ask for every status
  for (const u of ['charge.php?stat=' + encodeURIComponent('- a b c'), 'reception.php', 'reception.php?form=' + state.formId, 'admin/msgadm.php']) {
    await p.goto(`${MOD}/${u}`);
    await assertOk(p, u);
    assert.match(await bodyText(p), /Test Visitor|Contact us/, u);
  }
});

test('guest evaluates the replied message with the one-time password', async () => {
  const p = await guest.newPage();
  await p.goto(`${MOD}/message.php?id=${state.msgId}&p=${encodeURIComponent(state.onepass)}`);
  await assertOk(p, 'guest message');
  assert.ok(await p.$('input[name=eval]'), 'evaluation form is offered: ' + (await bodyText(p)).slice(0, 400));
  await p.check('input[name=eval][value="5"]');
  await clickAndSettle(p, 'form[action="status.php"] input[type=submit]');
  await p.waitForURL(/message\.php/, { timeout: 15000 });
  await eventually(() => h.sql(`SELECT value FROM icms_ccenter_message WHERE msgid=${state.msgId}`) === '5');
  await eventually(() => h.sql(`SELECT status FROM icms_ccenter_message WHERE msgid=${state.msgId}`) === 'c');
});

test('another visitor cannot open the message without the one-time password', async () => {
  const other = await browser.newContext();   // fresh session: no remembered password
  const p = await other.newPage();
  await p.goto(`${MOD}/message.php?id=${state.msgId}`);
  const t = await bodyText(p);
  assert.ok(!/Test Visitor/.test(t), 'message content must not be shown');
});

test('admin exports messages as CSV', async () => {
  // fetch from inside the page: a separate request client has another User-Agent, and ImpressCMS
  // would treat that as a hijacked session
  const p = await admin.newPage();
  await p.goto(`${MOD}/index.php`);
  const res = await p.evaluate(async (url) => {
    const r = await fetch(url, { credentials: 'include' });
    return { status: r.status, type: r.headers.get('content-type'), body: await r.text() };
  }, `${MOD}/export.php?form=${state.formId}&range=all`);
  assert.equal(res.status, 200);
  assert.match(res.type, /text\/csv/);
  assert.match(res.body, /Test Visitor/);
});

test('admin comments on the message', async () => {
  const p = await admin.newPage();
  await p.goto(`${MOD}/comment_new.php?com_itemid=${state.msgId}&com_order=1&com_mode=nest`);
  await assertOk(p, 'comment form');
  await p.fill('[name=com_text]', 'We have answered your question.');
  await clickAndSettle(p, '[name=com_dopost]');
  await assertOk(p, 'after comment');
  await eventually(() => h.sql(`SELECT count(*) FROM icms_xoopscomments WHERE com_itemid=${state.msgId}`) === '1');
  await eventually(() => Number(h.sql(`SELECT count(*) FROM icms_ccenter_log WHERE midref=${state.msgId} AND comment LIKE '%comid=%'`)) >= 1);   // comment is logged
});

test('admin deletes a message by setting status "deleted"', async () => {
  const m = await submitSample('To Be Deleted');
  const p = await admin.newPage();
  await p.goto(`${MOD}/message.php?id=${m.msgId}`);
  await p.selectOption('select[name=status]', 'x');
  await clickAndSettle(p, 'form[action="status.php"] input[type=submit]');
  await p.waitForURL(/reception\.php/, { timeout: 15000 });
  await eventually(() => h.sql(`SELECT status FROM icms_ccenter_message WHERE msgid=${m.msgId}`) === 'x');
  await p.goto(`${MOD}/message.php?id=${m.msgId}`);
  assert.ok(!/To Be Deleted/.test(await bodyText(p)), 'deleted message is no longer shown');
});

test('admin deletes a form', async () => {
  const p = await admin.newPage();
  const id = h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Smoke form'");
  await p.goto(`${MOD}/admin/index.php?op=delete&formid=${id}`);
  await assertOk(p, 'delete confirmation');
  await clickAndSettle(p, '.confirmMsg input[type=submit]');
  await assertOk(p, 'after delete');
  await eventually(() => h.sql(`SELECT count(*) FROM icms_ccenter_form WHERE formid=${id}`) === '0');
});

test('no PHP diagnostics were logged by module code', () => {
  assert.deepEqual(h.moduleLogLines(), []);
});
