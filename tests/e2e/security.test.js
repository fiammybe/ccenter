// Access control (who may use a form, who may see and handle messages) and CSRF protection.
//   NODE_PATH=$(npm root -g) BASE_URL=http://localhost:8083 COMPOSE_PROJECT=ccenter-83 node --test security.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const h = require('./helpers');
const { clickAndSettle, eventually, updateModule } = h;

const MOD = h.cfg.base + '/modules/ccenter';
const ERR_TEXT = /This page cannot be displayed due to an internal error|Fatal error|Parse error|Warning:|Deprecated:|Notice:/;
const TOKEN_ERR = /expired or was not sent from this site/;

let browser;
let admin;
let member;      // ordinary registered user
let memberId;
const openPages = [];
const pageOf = async (ctx) => { const p = await ctx.newPage(); openPages.push(p); return p; };
const text = async (p) => (await p.innerText('body')).replace(/\s+/g, ' ');
const perms = (formId) => h.sql(`SELECT IFNULL(group_concat(gperm_groupid ORDER BY gperm_groupid),'') FROM icms_group_permission WHERE gperm_name='form_view' AND gperm_itemid=${formId}`);

async function loginContext(user) {
  const ctx = await browser.newContext();
  const p = await pageOf(ctx);
  await h.login(p, user, h.cfg.pass);
  await p.close();
  return ctx;
}
// POST from inside a page of the site (same cookies and User-Agent as the browser session)
async function post(p, url, fields) {
  return p.evaluate(async ([u, f]) => {
    const r = await fetch(u, { method: 'POST', body: new URLSearchParams(f), credentials: 'include', redirect: 'manual' });
    return { status: r.status, text: r.type === 'opaqueredirect' ? '' : (await r.text()) };
  }, [url, fields]);
}
async function tokenFrom(p, url) {
  if (p.url() === 'about:blank') await p.goto(`${MOD}/index.php`);   // fetch needs a page of the site
  const html = await p.evaluate((u) => fetch(u, { credentials: 'include' }).then((r) => r.text()), url);
  const m = html.match(/name=["']XOOPS_TOKEN_REQUEST["'][^>]*value=["']([0-9a-f]+)["']/) || html.match(/value=["']([0-9a-f]{32})["'][^>]*name=["']XOOPS_TOKEN_REQUEST["']/);
  assert.ok(m, 'a token is on the page ' + url);
  return m[1];
}
const plain = (t) => t.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');

test.afterEach(async () => { while (openPages.length) await openPages.pop().close().catch(() => {}); });
test.before(async () => {
  browser = await chromium.launch();
  h.sql("DELETE FROM icms_ccenter_form WHERE title<>'Contact us'; TRUNCATE icms_ccenter_message; TRUNCATE icms_ccenter_log; TRUNCATE icms_protector_access");
  memberId = h.createUser('tester', [2]);
  h.sql(`DELETE FROM icms_group_permission WHERE gperm_name='form_view' AND gperm_itemid>1`);
  admin = await loginContext(h.cfg.admin);
  member = await loginContext('tester');
  h.clearPhpLog();
});
test.after(async () => { await browser.close(); });

test('forms of the earlier version (access kept as text) can be used, and are converted to group permissions by the update', async () => {
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,defs,grpperm,priuid,optvars) VALUES (70,1,'Old access','Question*','|3|2|',1,'{}')`);
  const guest = await browser.newContext();
  const g = await pageOf(guest);
  await g.goto(`${MOD}/index.php?form=70`);
  assert.ok(await g.$('[name=cc1]'), 'before the update the text decides');
  assert.equal(perms(70), '');

  const p = await pageOf(admin);
  const out = await updateModule(p);
  assert.ok(!ERR_TEXT.test(out), out.slice(0, 300));
  assert.equal(perms(70), '2,3');
  assert.equal(h.sql('SELECT grpperm FROM icms_ccenter_form WHERE formid=70'), '|2|3|', 'the text is kept, in step');
  await updateModule(p);
  assert.equal(perms(70), '2,3', 'repeating the update changes nothing');

  await g.goto(`${MOD}/index.php?form=70`);
  assert.ok(await g.$('[name=cc1]'), 'after the update the permission decides');

  // taking the anonymous group away from the permission closes the form for guests, not for members
  h.sql("DELETE FROM icms_group_permission WHERE gperm_name='form_view' AND gperm_itemid=70 AND gperm_groupid=3");
  await g.goto(`${MOD}/index.php?form=70`);
  assert.ok(!(await g.$('[name=cc1]')), 'guests are out');
  const m = await pageOf(member);
  await m.goto(`${MOD}/index.php?form=70`);
  assert.ok(await m.$('[name=cc1]'), 'members are in');
  await guest.close();
});

test('the administrator chooses the groups; they become group permissions', async () => {
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php?op=edit`);
  await p.fill('[name=title]', 'Members only');
  await p.fill('[name=defs]', 'Subject*');
  await p.selectOption('[name="grpperm[]"]', ['2']);
  await clickAndSettle(p, '[name=formdefs]');
  const id = await eventually(() => h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Members only'"));
  await eventually(() => perms(id) === '2');
  assert.equal(h.sql(`SELECT grpperm FROM icms_ccenter_form WHERE formid=${id}`), '|2|');

  const guest = await browser.newContext();
  const g = await pageOf(guest);
  await g.goto(`${MOD}/index.php?form=${id}`);
  assert.ok(!(await g.$('[name=cc1]')), 'guests do not get the form');
  const m = await pageOf(member);
  await m.goto(`${MOD}/index.php?form=${id}`);
  assert.ok(await m.$('[name=cc1]'), 'members do');

  // editing shows the groups again, and changing them changes the permission
  await p.goto(`${MOD}/admin/index.php?formid=${id}`);
  assert.deepEqual(await p.$$eval('[name="grpperm[]"] option:checked', (o) => o.map((x) => x.value)), ['2']);
  await p.selectOption('[name="grpperm[]"]', ['2', '3']);
  await clickAndSettle(p, '[name=formdefs]');
  await eventually(() => perms(id) === '2,3');
  await g.goto(`${MOD}/index.php?form=${id}`);
  assert.ok(await g.$('[name=cc1]'), 'guests now get it too');
  await guest.close();
});

test('deleting a form removes its permissions', async () => {
  const id = h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Members only'");
  assert.notEqual(perms(id), '');
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php?op=delete&formid=${id}`);
  await clickAndSettle(p, '.confirmMsg input[type=submit]');
  await eventually(() => h.sql(`SELECT count(*) FROM icms_ccenter_form WHERE formid=${id}`) === '0');
  assert.equal(perms(id), '');
});

test('a form submission without a valid token is refused and nothing is stored', async () => {
  const guest = await browser.newContext();
  const g = await pageOf(guest);
  await g.goto(`${MOD}/index.php?form=1`);
  const fields = { op: 'store', cc1: 'Eve', cc2: 'eve@example.com', cc2_conf: 'eve@example.com', cc3: 'Others', cc4: 'forged' };
  const r = await post(g, `${MOD}/index.php?form=1`, fields);
  assert.match(plain(r.text), TOKEN_ERR);
  assert.equal(h.sql("SELECT count(*) FROM icms_ccenter_message WHERE body LIKE '%forged%'"), '0');

  const wrong = await post(g, `${MOD}/index.php?form=1`, { ...fields, XOOPS_TOKEN_REQUEST: '0123456789abcdef0123456789abcdef' });
  assert.match(plain(wrong.text), TOKEN_ERR);
  assert.equal(h.sql("SELECT count(*) FROM icms_ccenter_message WHERE body LIKE '%forged%'"), '0');

  // the same request with the token of the form works (a token is used once)
  const token = await tokenFrom(g, `${MOD}/index.php?form=1`);
  await post(g, `${MOD}/index.php?form=1`, { ...fields, XOOPS_TOKEN_REQUEST: token });
  await eventually(() => h.sql("SELECT count(*) FROM icms_ccenter_message WHERE body LIKE '%forged%'") === '1');
  const again = await post(g, `${MOD}/index.php?form=1`, { ...fields, cc4: 'replayed', XOOPS_TOKEN_REQUEST: token });
  assert.match(plain(again.text), TOKEN_ERR, 'a token cannot be used twice');
  assert.equal(h.sql("SELECT count(*) FROM icms_ccenter_message WHERE body LIKE '%replayed%'"), '0');
  await guest.close();
});

test('changing a status needs a token', async () => {
  h.sql(`INSERT INTO icms_ccenter_message (msgid,uid,touid,ctime,mtime,atime,fidref,email,body,status,onepass) VALUES (710,0,1,1,1,1,1,'','','a','pass0710')`);
  const p = await pageOf(admin);
  await p.goto(`${MOD}/message.php?id=710`);
  await post(p, `${MOD}/status.php`, { id: '710', status: 'b' });
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710'), 'a', 'no token: unchanged');
  const token = await tokenFrom(p, `${MOD}/message.php?id=710`);
  await post(p, `${MOD}/status.php`, { id: '710', status: 'b', XOOPS_TOKEN_REQUEST: token });
  await eventually(() => h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710') === 'b');
  // GET requests change nothing
  const before = h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710');
  await p.evaluate(async (u) => fetch(u, { credentials: 'include' }), `${MOD}/status.php?id=710&status=c`);
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710'), before);
});

test('the administration needs a token for changes', async () => {
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php`);
  // new form
  await post(p, `${MOD}/admin/index.php`, { formdefs: '1', formid: '0', title: 'Forged form', description: 'x', defs: 'A', priuid: '1', cgroup: '0', store: '1', custom: '0', weight: '0', active: '1' });
  assert.equal(h.sql("SELECT count(*) FROM icms_ccenter_form WHERE title='Forged form'"), '0');
  // delete
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,defs,grpperm,priuid) VALUES (72,1,'Keep me','A','|2|',1)`);
  await post(p, `${MOD}/admin/index.php`, { op: 'delform', formid: '72' });
  assert.equal(h.sql('SELECT count(*) FROM icms_ccenter_form WHERE formid=72'), '1');
  // message administration
  await post(p, `${MOD}/admin/msgadm.php`, { store: '1', msgid: '710', touid: '1', status: 'x' });
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710'), 'b');
  await post(p, `${MOD}/admin/msgadm.php`, { op: 'x', 'ids[]': '710' });
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710'), 'b');
  // with a token the same requests work
  const token = await tokenFrom(p, `${MOD}/admin/msgadm.php?id=710`);
  await post(p, `${MOD}/admin/msgadm.php`, { store: '1', msgid: '710', touid: '1', status: 'c', XOOPS_TOKEN_REQUEST: token });
  await eventually(() => h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710') === 'c');
});

test('the message administration refuses a status or person in charge that does not exist', async () => {
  const p = await pageOf(admin);
  const token = await tokenFrom(p, `${MOD}/admin/msgadm.php?id=710`);
  await post(p, `${MOD}/admin/msgadm.php`, { store: '1', msgid: '710', touid: '1', status: 'zzz', XOOPS_TOKEN_REQUEST: token });
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=710'), 'c');
  const token2 = await tokenFrom(p, `${MOD}/admin/msgadm.php?id=710`);
  await post(p, `${MOD}/admin/msgadm.php`, { store: '1', msgid: '710', touid: '99999', status: 'c', XOOPS_TOKEN_REQUEST: token2 });
  assert.equal(h.sql('SELECT touid FROM icms_ccenter_message WHERE msgid=710'), '1');
});

test('an ordinary member cannot see, change or take over other people’s messages', async () => {
  h.sql(`INSERT INTO icms_ccenter_message (msgid,uid,touid,ctime,mtime,atime,fidref,email,body,status,onepass) VALUES (720,0,1,1,1,1,1,'','','a','pass0720')`);
  h.sql(`INSERT INTO icms_ccenter_message (msgid,uid,touid,ctime,mtime,atime,fidref,email,body,status,onepass) VALUES (721,0,0,1,1,1,1,'','','-','pass0721')`);
  const m = await pageOf(member);

  await m.goto(`${MOD}/message.php?id=720`);
  assert.doesNotMatch(m.url(), /message\.php\?id=720$/, 'redirected away from the message');
  assert.match(await text(m), /Contact us|permission|Contact Center/);

  await m.goto(`${MOD}/index.php`);
  const token = await tokenFrom(m, `${MOD}/index.php?form=1`);
  await post(m, `${MOD}/status.php`, { id: '720', status: 'c', XOOPS_TOKEN_REQUEST: token });
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=720'), 'a', 'not in charge: unchanged');

  const token2 = await tokenFrom(m, `${MOD}/index.php?form=1`);
  await post(m, `${MOD}/status.php`, { id: '721', op: 'myself', XOOPS_TOKEN_REQUEST: token2 });
  assert.equal(h.sql('SELECT touid FROM icms_ccenter_message WHERE msgid=721'), '0', 'not in the contact group: not taken over');

  const csv = await m.evaluate(async (u) => (await fetch(u, { credentials: 'include' })).text(), `${MOD}/export.php?form=1&range=all`);
  assert.doesNotMatch(csv, /^"ID"/, 'no export of a form the member does not handle');

  // the contact group of the form decides
  h.sql(`UPDATE icms_ccenter_form SET cgroup=2 WHERE formid=1`);
  const m2 = await pageOf(member);
  await m2.goto(`${MOD}/message.php?id=720`);
  assert.match(m2.url(), /message\.php\?id=720/, 'members of the contact group may see it');
  const token3 = await tokenFrom(m2, `${MOD}/message.php?id=720`);
  await post(m2, `${MOD}/status.php`, { id: '721', op: 'myself', XOOPS_TOKEN_REQUEST: token3 });
  await eventually(() => h.sql('SELECT touid FROM icms_ccenter_message WHERE msgid=721') === String(memberId));
  const csv2 = await m2.evaluate(async (u) => (await fetch(u, { credentials: 'include' })).text(), `${MOD}/export.php?form=1&range=all`);
  assert.match(csv2, /^"ID"/, 'the contact group may export');
  h.sql(`UPDATE icms_ccenter_form SET cgroup=0 WHERE formid=1`);
});

test('a guest cannot reach the staff pages or the administration', async () => {
  const guest = await browser.newContext();
  const g = await pageOf(guest);
  for (const u of ['reception.php', 'charge.php', 'list.php', 'export.php?form=1&range=all', 'admin/index.php', 'admin/msgadm.php']) {
    await g.goto(`${MOD}/${u}`);
    const t = await text(g);
    assert.ok(!/Form name|Count Wait Work|"ID","Posted"/.test(t), `${u} must not show staff data: ${t.slice(0, 120)}`);
  }
  await guest.close();
});

test('no PHP diagnostics were logged by module code', () => {
  assert.deepEqual(h.moduleLogLines(), []);
});
