// The administration: form list and editor, message list and details, export.
//   NODE_PATH=$(npm root -g) BASE_URL=http://localhost:8083 COMPOSE_PROJECT=ccenter-83 node --test admin.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const h = require('./helpers');
const { clickAndSettle, eventually, updateModule } = h;

const MOD = h.cfg.base + '/modules/ccenter';
const ERR_TEXT = /This page cannot be displayed due to an internal error|Fatal error|Parse error|Warning:|Deprecated:|Notice:|You have an error in your SQL/;
const q = (s) => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

let browser;
let admin;
const openPages = [];
const pageOf = async (ctx) => { const p = await ctx.newPage(); openPages.push(p); return p; };
const text = async (p) => (await p.innerText('body')).replace(/\s+/g, ' ');
async function assertOk(p, label) { const t = await text(p); assert.ok(!ERR_TEXT.test(t), `${label}: ${t.slice(0, 300)}`); }
const now = () => Math.floor(Date.now() / 1000);
const day = 86400;

test.afterEach(async () => { while (openPages.length) await openPages.pop().close().catch(() => {}); });
test.before(async () => {
  browser = await chromium.launch();
  admin = await browser.newContext();
  h.sql("DELETE FROM icms_ccenter_form WHERE title<>'Contact us'; TRUNCATE icms_ccenter_message; TRUNCATE icms_ccenter_log; TRUNCATE icms_protector_access; DELETE FROM icms_xoopscomments");
  h.createUser('tester', [2]);
  const lp = await pageOf(admin);
  await h.login(lp);
  await updateModule(lp);     // templates of this version
  await lp.close();
  h.clearPhpLog();
});
test.after(async () => { await browser.close(); });

test('the form list is a table with counts, sorting and quick search', async () => {
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,defs,grpperm,priuid,cgroup,weight,store,optvars) VALUES
         (80,1,'Zebra form','A','|2|',0,2,5,1,'{}'),(81,1,'Alpha form','A','|2|',-2,0,6,1,'{}'),(82,1,'No store','A','|2|',1,0,7,2,'{}')`);
  h.sql(`INSERT INTO icms_ccenter_message (msgid,fidref,ctime,mtime,atime,body,status) VALUES
         (800,80,1,1,1,'','-'),(801,80,1,1,1,'','a'),(802,80,1,1,1,'','a'),(803,80,1,1,1,'','x')`);
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php`);
  await assertOk(p, 'list');
  const rows = await p.$$eval('table.outer tr', (trs) => trs.map((tr) => [...tr.children].map((c) => c.innerText.trim())));
  const zebra = rows.find((r) => r[1] === 'Zebra form');
  assert.ok(zebra, 'the list shows the form: ' + JSON.stringify(rows.slice(0, 3)));
  // columns: id, title, contact, all, waiting, working, replied, closed, active
  assert.deepEqual(zebra.slice(3, 8), ['3', '1', '2', '0', '0'], 'deleted messages are not counted');
  assert.match(zebra[2], /Registered Users/, 'a contact group in brackets');
  assert.match(rows.find((r) => r[1] === 'Alpha form')[2], /Member \[/, 'a member of a group');
  assert.equal(rows.find((r) => r[1] === 'No store')[3], '--', 'nothing is counted for a form that stores nothing');

  // sort by title (header link), quick search
  await p.goto(`${MOD}/admin/index.php?form_sortsel=title&form_ordersel=ASC`);
  const titles = await p.$$eval('table.outer tr td.title', (t) => t.map((x) => x.innerText.trim()));
  assert.deepEqual(titles.filter((t) => /form|store|us/i.test(t)), [...titles].sort((a, b) => a.localeCompare(b)).filter((t) => /form|store|us/i.test(t)));
  await p.goto(`${MOD}/admin/index.php`);
  await p.fill('input[name^=quicksearch_]', 'Alpha');
  await clickAndSettle(p, 'input[name^=button_quicksearch_]');
  const found = await p.$$eval('table.outer tr td.title', (t) => t.map((x) => x.innerText.trim()));
  assert.deepEqual(found, ['Alpha form']);
});

test('a hostile sort or filter in the address cannot reach the database', async () => {
  const p = await pageOf(admin);
  for (const qs of [
    "form_sortsel=title%20DESC%2C%20(SELECT%20SLEEP(5))", "form_ordersel=ASC%3B%20DROP%20TABLE%20x", "filtersel=title&filtersel2=%27%20OR%201%3D1%20--%20", "limitsel=1%3BSELECT%201",
  ]) {
    const started = Date.now();
    await p.goto(`${MOD}/admin/index.php?${qs}`);
    await assertOk(p, qs);
    assert.ok(Date.now() - started < 4500, 'no injected SLEEP: ' + qs);
  }
  for (const qs of ["message_sortsel=mtime%20DESC%2C%20(SELECT%20SLEEP(5))", "filtersel=fidref&filtersel2=1%20OR%201%3D1", "message_ordersel=%3Bdrop"]) {
    const started = Date.now();
    await p.goto(`${MOD}/admin/msgadm.php?${qs}`);
    await assertOk(p, qs);
    assert.ok(Date.now() - started < 4500, 'no injected SLEEP: ' + qs);
  }
  assert.equal(h.sql('SELECT count(*) FROM icms_ccenter_form'), '4');
});

test('a form can be copied', async () => {
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php?op=clone&formid=80`);
  await assertOk(p, 'clone');
  assert.equal(await p.inputValue('[name=formid]'), '0', 'saving creates a new form');
  assert.equal(await p.inputValue('[name=title]'), 'Copy of Zebra form');
  assert.equal(await p.inputValue('[name=defs]'), 'A');
  await clickAndSettle(p, '[name=modify_button], [name=create_button]');
  await eventually(() => h.sql("SELECT count(*) FROM icms_ccenter_form WHERE title='Copy of Zebra form'") === '1');
  const copy = h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Copy of Zebra form'");
  assert.notEqual(copy, '80');
  assert.equal(h.sql(`SELECT count(*) FROM icms_ccenter_message WHERE fidref=${copy}`), '0', 'no messages are copied');
  assert.notEqual(h.sql(`SELECT IFNULL(group_concat(gperm_groupid),'') FROM icms_group_permission WHERE gperm_name='form_view' AND gperm_itemid=${copy}`), '', 'the access groups are copied');
});

test('the definition can be edited as lines or as JSON', async () => {
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php?op=mod&formid=1`);
  const lines = await p.inputValue('[name=defs]');
  assert.match(lines, /^Your name\*,size=40/m);
  await p.click('a:has-text("JSON")');
  await p.waitForLoadState('load');
  const json = JSON.parse(await p.inputValue('[name=defs]'));
  assert.equal(json.version, 1);
  assert.equal(json.items[0].key, 'Your name');

  // JSON that is changed and saved
  json.items.push({ key: 'Phone', type: 'text', label: 'Phone number', attrs: { size: 15 } });
  await p.fill('[name=defs]', JSON.stringify(json, null, 2));
  await clickAndSettle(p, '[name=modify_button], [name=create_button]');
  await eventually(() => h.sql('SELECT defs FROM icms_ccenter_form WHERE formid=1').includes('Phone number'));
  await p.goto(`${MOD}/admin/index.php?op=mod&formid=1`);
  assert.match(await p.inputValue('[name=defs]'), /^Phone=Phone number,size=15$/m, 'shown as a line again');

  // broken JSON is refused, with a message, and what was typed stays
  await p.fill('[name=defs]', '{"version":1,"items":[{"key":"A"},{"key":"A"}]');
  await clickAndSettle(p, '[name=modify_button], [name=create_button]');
  assert.match(await text(p), /not valid JSON/);
  assert.equal(await p.inputValue('[name=defs]'), '{"version":1,"items":[{"key":"A"},{"key":"A"}]');
  assert.ok(h.sql('SELECT defs FROM icms_ccenter_form WHERE formid=1').includes('Phone number'), 'stored definition is untouched');
  // restore the sample definition
  await p.goto(`${MOD}/admin/index.php?op=mod&formid=1`);
  await p.fill('[name=defs]', lines);
  await clickAndSettle(p, '[name=modify_button], [name=create_button]');
  await eventually(() => !h.sql('SELECT defs FROM icms_ccenter_form WHERE formid=1').includes('Phone number'));
});

test('the contact person is chosen from the members of the contact group', async () => {
  const testerId = h.sql("SELECT uid FROM icms_users WHERE uname='tester'");
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php?op=mod&formid=1`);
  const options = async () => p.$$eval('[name=priuid] option', (o) => o.map((x) => [x.value, x.innerText]));
  const before = await options();
  assert.ok(before.some(([v]) => v === '0'), 'nobody');
  assert.ok(before.some(([v, t]) => v === '-2' && /Registered/.test(t)), 'a member of a group (chosen by the link)');
  assert.ok(!before.some(([v]) => v === testerId), 'the member is not in the list of the webmasters');
  await Promise.all([p.waitForNavigation(), p.selectOption('[name=cgroup]', '2')]);   // the select reloads the form
  await assertOk(p, 'after change');
  assert.ok((await options()).some(([v, t]) => v === testerId && t === 'tester'), 'the members of the contact group are offered');
  assert.equal(h.sql('SELECT cgroup FROM icms_ccenter_form WHERE formid=1'), '0', 'nothing was saved by "change"');
  // saving with that member as contact person
  await p.selectOption('[name=priuid]', testerId);
  await clickAndSettle(p, '[name=modify_button], [name=create_button]');
  await eventually(() => h.sql('SELECT priuid FROM icms_ccenter_form WHERE formid=1') === testerId);
  h.sql('UPDATE icms_ccenter_form SET priuid=1, cgroup=0 WHERE formid=1');
});

test('the preview of a form shows it without saving', async () => {
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/index.php?op=mod&formid=1`);
  await p.fill('[name=title]', 'Only a preview');
  await clickAndSettle(p, '[name=preview]');
  await assertOk(p, 'preview');
  assert.match(await text(p), /Preview/i);
  assert.equal(h.sql('SELECT title FROM icms_ccenter_form WHERE formid=1'), 'Contact us');
});

test('the message list filters, sorts, searches and keeps its filters on the next page', async () => {
  const mk = (id, form, status, name, mtime, email = '') => h.sql(`INSERT INTO icms_ccenter_message (msgid,fidref,uid,touid,ctime,mtime,atime,email,body,status) VALUES (${id},${form},0,1,${mtime},${mtime},1,${q(email)},${q(JSON.stringify({ version: 1, fields: [{ key: 'Name', label: 'Name', type: 'text', value: name, display: name }] }))},'${status}')`);
  h.sql('TRUNCATE icms_ccenter_message');
  for (let i = 1; i <= 30; i++) mk(900 + i, 80, i % 2 ? 'a' : 'c', 'Person ' + String(i).padStart(2, '0'), 1000 + i, `p${i}@example.com`);
  mk(950, 81, '-', 'Other form', 5000, 'other@example.com');
  mk(951, 80, 'x', 'Deleted one', 5001);
  const p = await pageOf(admin);

  await p.goto(`${MOD}/admin/msgadm.php?stat=${encodeURIComponent('- a b c')}&formid=80`);
  await assertOk(p, 'list');
  let names = await p.$$eval('table.outer td.fidref', (t) => t.map((x) => x.innerText));
  assert.ok(names.length > 0 && names.every((n) => /Zebra form/.test(n)), 'only the chosen form');
  assert.ok(!names.some((n) => /Deleted one/.test(n)), 'deleted messages stay out');

  // the newest first; the second page keeps the filter and the status
  assert.match(names[0], /Person 30/);
  await p.click('a:has-text("2")');
  await p.waitForLoadState('load');
  names = await p.$$eval('table.outer td.fidref', (t) => t.map((x) => x.innerText));
  assert.ok(names.length > 0 && names.every((n) => /Zebra form/.test(n)), 'page two: still only that form');

  // status filter: closed only
  await p.goto(`${MOD}/admin/msgadm.php?stat=c&filtersel=fidref&filtersel2=80&start=0`);
  names = await p.$$eval('table.outer td.fidref', (t) => t.map((x) => x.innerText));
  assert.ok(names.length === 15 && names.every((n) => /Person (\d*[02468])\b/.test(n)), 'even numbers have status c');

  // quick search in the stored text and in the address
  await p.goto(`${MOD}/admin/msgadm.php?stat=${encodeURIComponent('- a b c')}&filtersel=default`);
  await p.fill('input[name^=quicksearch_]', 'other@example');
  await clickAndSettle(p, 'input[name^=button_quicksearch_]');
  names = await p.$$eval('table.outer td.fidref', (t) => t.map((x) => x.innerText));
  assert.equal(names.length, 1);
  assert.match(names[0], /Alpha form/);

  // sort by date, oldest first
  await p.goto(`${MOD}/admin/msgadm.php?stat=${encodeURIComponent('- a b c')}&filtersel=default&message_sortsel=mtime&message_ordersel=ASC&limitsel=40`);
  names = await p.$$eval('table.outer td.fidref', (t) => t.map((x) => x.innerText));
  assert.match(names[0], /Person 01/);
  h.sql("DELETE FROM icms_ccenter_message WHERE msgid=951");
});

test('with the selected: change the status or delete, with a token', async () => {
  h.sql('TRUNCATE icms_ccenter_message');
  for (let i = 1; i <= 3; i++) h.sql(`INSERT INTO icms_ccenter_message (msgid,fidref,ctime,mtime,atime,body,status) VALUES (${960 + i},80,1,1,1,'','a')`);
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/msgadm.php?stat=${encodeURIComponent('- a b c')}&filtersel=default&limitsel=40`);
  const boxes = await p.$$('input[name="selected_icms_persistableobjects[]"]');
  assert.equal(boxes.length, 3);
  await p.check('#checkall_icmspersistableobjects');
  assert.equal((await p.$$eval('input[name="selected_icms_persistableobjects[]"]:checked', (b) => b.length)), 3, 'check all works');
  await p.uncheck(`input[name="selected_icms_persistableobjects[]"][value="963"]`);
  await p.selectOption('[name=bulk_action]', 'b');
  await clickAndSettle(p, '[name=bulk]');
  await eventually(() => h.sql("SELECT group_concat(status ORDER BY msgid) FROM icms_ccenter_message") === 'b,b,a');
  assert.ok(Number(h.sql('SELECT count(*) FROM icms_ccenter_log WHERE midref IN (961,962)')) >= 2, 'logged');

  await p.goto(`${MOD}/admin/msgadm.php?stat=${encodeURIComponent('- a b c')}&filtersel=default&limitsel=40`);
  await p.check(`input[name="selected_icms_persistableobjects[]"][value="961"]`);
  await p.selectOption('[name=bulk_action]', 'purge');
  await clickAndSettle(p, '[name=bulk]');
  await eventually(() => h.sql('SELECT group_concat(msgid ORDER BY msgid) FROM icms_ccenter_message') === '962,963');
});

test('a message can be changed and deleted from its detail page and the delete link', async () => {
  h.sql("UPDATE icms_ccenter_message SET touid=0, status='-' WHERE msgid=962");
  const testerId = h.sql("SELECT uid FROM icms_users WHERE uname='tester'");
  h.sql(`UPDATE icms_ccenter_form SET cgroup=2 WHERE formid=80`);
  const p = await pageOf(admin);
  await p.goto(`${MOD}/admin/msgadm.php?msgid=962`);
  await assertOk(p, 'detail');
  await p.selectOption('[name=status]', 'a');
  await p.selectOption('[name=touid]', testerId);
  await clickAndSettle(p, '[name=store]');
  await eventually(() => h.sql('SELECT touid FROM icms_ccenter_message WHERE msgid=962') === testerId);
  assert.equal(h.sql('SELECT status FROM icms_ccenter_message WHERE msgid=962'), 'a');
  await p.goto(`${MOD}/admin/msgadm.php?msgid=962`);
  assert.match(await text(p), /tester/, 'the history mentions the new person in charge');
  h.sql('UPDATE icms_ccenter_form SET cgroup=0 WHERE formid=80');

  // delete link: asks, and needs a token
  await p.goto(`${MOD}/admin/msgadm.php?op=del&msgid=963`);
  await clickAndSettle(p, '.confirmMsg input[type=submit]');
  await eventually(() => h.sql('SELECT count(*) FROM icms_ccenter_message WHERE msgid=963') === '0');
});

test('export: only the chosen period, columns follow the form, address from the message', async () => {
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,defs,grpperm,priuid,optvars) VALUES (90,1,'Export form','Name*\\nEmail*,mail','|2|',1,'{}')`);
  h.sql('TRUNCATE icms_ccenter_message');
  const body = (name) => q(JSON.stringify({ version: 1, fields: [{ key: 'Name', label: 'Name', type: 'text', value: name, display: name }] }));
  const t = now();
  h.sql(`INSERT INTO icms_ccenter_message (msgid,fidref,ctime,mtime,atime,email,body,status) VALUES
    (990,90,${t - 3600},${t},1,'now@example.com',${body('This month')},'a'),
    (991,90,${t - 40 * day},${t},1,'old@example.com',${body('Last month or older')},'a'),
    (992,90,${t - 400 * day},${t},1,'older@example.com',${body('Last year')},'a'),
    (993,90,${t - 3600},${t},1,'gone@example.com',${body('Deleted')},'x')`);
  const p = await pageOf(admin);
  await p.goto(`${MOD}/index.php`);
  const csv = (range) => p.evaluate(async (u) => (await fetch(u, { credentials: 'include' })).text(), `${MOD}/export.php?form=90&range=${encodeURIComponent(range)}`);

  const all = await csv('all');
  assert.match(all, /This month/);
  assert.match(all, /Last month or older/);
  assert.match(all, /Last year/);
  assert.doesNotMatch(all, /Deleted/);
  assert.match(all, /now@example\.com/, 'the address of the message fills the mail column');
  assert.match(all.split('\n')[0], /"Name\*?".*"Email/s, 'columns follow the definition');

  const thisYear = await csv('y0');
  assert.match(thisYear, /This month/);
  assert.doesNotMatch(thisYear, /Last year/);
  const lastYear = await csv('y1');
  assert.doesNotMatch(lastYear, /This month/);
  assert.match(lastYear, /Last year/);
  assert.doesNotMatch(await csv('m0'), /Last month or older/);
  assert.match(await csv('m0'), /This month/);
  const injected = await csv("m0' OR '1'='1");
  assert.match(injected, /Last year/, 'an unknown range means everything, not an injected condition');
});

test('the administration logs no PHP diagnostics', () => {
  assert.deepEqual(h.moduleLogLines(), []);
});
