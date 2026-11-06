// Tests of how forms, options and messages are stored (JSON), of the migration of data written by
// earlier versions, and of the file download.
//   NODE_PATH=$(npm root -g) BASE_URL=http://localhost:8083 COMPOSE_PROJECT=ccenter-83 node --test data.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const h = require('./helpers');
const { clickAndSettle, eventually, updateModule } = h;

const MOD = h.cfg.base + '/modules/ccenter';
const ERR_TEXT = /This page cannot be displayed due to an internal error|Fatal error|Parse error|Warning:|Deprecated:|Notice:/;
const q = (s) => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";   // SQL string literal
const J = (s) => JSON.parse(s);

let browser;
let admin;
const openPages = [];
async function page(ctx) { const p = await ctx.newPage(); openPages.push(p); return p; }
async function text(p) { return (await p.innerText('body')).replace(/\s+/g, ' '); }
async function assertOk(p, label) { const t = await text(p); assert.ok(!ERR_TEXT.test(t), `${label}: ${t.slice(0, 300)}`); }

// What an earlier version wrote.
const LEGACY_DEFS = [
  'Your name*,size=40',
  'Email*,mail',
  'Kind*,radio,a=Question,b+=Complaint',
  'Topics,checkbox,n=News,f=Forum,g=Gallery',
  'Message,textarea,rows=4',
].join('\n');
const LEGACY_BODY = 'Your name: Marie Curie\nEmail: marie@example.com\nKind: b\nTopics: n, g\nMessage: line one\n\tline two\n';

test.afterEach(async () => { while (openPages.length) await openPages.pop().close().catch(() => {}); });
test.before(async () => {
  browser = await chromium.launch();
  admin = await browser.newContext();
  h.sql("DELETE FROM icms_ccenter_form WHERE title<>'Contact us'; TRUNCATE icms_ccenter_message; TRUNCATE icms_ccenter_log; TRUNCATE icms_protector_access");
  const lp = await page(admin);
  await h.login(lp);
  await lp.close();
  h.clearPhpLog();
});
test.after(async () => { await browser.close(); });

test('data of earlier versions shows the same before and after the module update, and the update can be repeated', async () => {
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,description,defs,grpperm,priuid,cgroup,store,optvars)
         VALUES (50,1,'Legacy form','',${q(LEGACY_DEFS)},'|3|2|',1,0,1,${q('redirect: /thanks.php\nnotify_with_email: 1\n')})`);
  h.sql(`INSERT INTO icms_ccenter_message (msgid,uid,touid,ctime,mtime,atime,fidref,email,body,status,onepass)
         VALUES (500,0,1,1,1,1,50,'marie@example.com',${q(LEGACY_BODY)},'a','legacy01')`);
  h.sql(`INSERT INTO icms_ccenter_message (msgid,uid,touid,ctime,mtime,atime,fidref,email,body,status,onepass)
         VALUES (501,0,1,1,1,1,50,'',${q('')},'a','legacy02')`);   // a form that does not keep the message

  const p = await page(admin);
  const view = async () => { await p.goto(`${MOD}/message.php?id=500`); await assertOk(p, 'message'); return (await p.innerText('.attr, .evform, #content, body')).replace(/\s+/g, ' '); };
  const before = await text(await (async () => { await p.goto(`${MOD}/message.php?id=500`); return p; })());
  assert.match(before, /Marie Curie/);
  assert.match(before, /Complaint/, 'the option label, not its key, is shown');
  assert.match(before, /News, Gallery/);
  assert.match(before, /line one line two/);

  const out = await updateModule(p);
  assert.ok(!ERR_TEXT.test(out), out.slice(0, 400));
  assert.match(out, /Converted/);

  const body = J(h.sql('SELECT body FROM icms_ccenter_message WHERE msgid=500'));
  assert.equal(body.version, 1);
  const byKey = Object.fromEntries(body.fields.map((f) => [f.key, f]));
  assert.deepEqual(byKey['Topics'].value, ['n', 'g']);
  assert.equal(byKey['Topics'].display, 'News, Gallery');
  assert.equal(byKey['Kind'].value, 'b');
  assert.equal(byKey['Kind'].display, 'Complaint');
  assert.equal(byKey['Message'].value, 'line one\nline two');
  assert.equal(byKey['Your name'].label, 'Your name');
  assert.equal(h.sql('SELECT body FROM icms_ccenter_message WHERE msgid=501'), '', 'empty bodies stay empty');

  const defs = J(h.sql('SELECT defs FROM icms_ccenter_form WHERE formid=50'));
  assert.equal(defs.version, 1);
  assert.deepEqual(defs.items.map((i) => i.key), ['Your name', 'Email', 'Kind', 'Topics', 'Message']);
  assert.deepEqual(J(h.sql('SELECT optvars FROM icms_ccenter_form WHERE formid=50')), { redirect: '/thanks.php', notify_with_email: '1' });

  await p.goto(`${MOD}/message.php?id=500`);
  const after = await text(p);
  for (const expected of [/Marie Curie/, /Complaint/, /News, Gallery/, /line one line two/]) assert.match(after, expected);

  // second run: nothing changes
  const snapshot = h.sql('SELECT md5(group_concat(body)) FROM icms_ccenter_message') + h.sql('SELECT md5(group_concat(defs,optvars)) FROM icms_ccenter_form');
  const again = await updateModule(p);
  assert.ok(!ERR_TEXT.test(again), again.slice(0, 400));
  assert.doesNotMatch(again, /Converted <b>\d+<\/b> message|Converted the definition/);
  assert.equal(h.sql('SELECT md5(group_concat(body)) FROM icms_ccenter_message') + h.sql('SELECT md5(group_concat(defs,optvars)) FROM icms_ccenter_form'), snapshot);
});

test('the preference "default options" is JSON and the earlier form is still understood', async () => {
  const mid = h.sql("SELECT mid FROM icms_modules WHERE dirname='ccenter'");
  const stored = h.sql(`SELECT conf_value FROM icms_config WHERE conf_modid=${mid} AND conf_name='def_attrs'`);
  const prefs = J(stored.replace(/<!--.*?-->/g, ''));
  assert.deepEqual(Object.fromEntries(Object.entries(prefs).map(([k, v]) => [k, String(v)])), { size: '60', rows: '5', cols: '50', notify_with_email: '0' });
});

test('a submission is stored as JSON: checkbox as a list of option keys, labels as sent', async () => {
  const ctx = await browser.newContext();
  const p = await page(ctx);
  await p.goto(`${MOD}/index.php?form=50`);
  await p.fill('[name=cc1]', 'Ada Lovelace');
  await p.fill('[name=cc2]', 'ada@example.com');
  await p.fill('[name=cc2_conf]', 'ada@example.com');
  await p.check('[name=cc3][value=a]');
  await p.check('[name=cc4\\[\\]][value=f]');
  await p.check('[name=cc4\\[\\]][value=g]');
  await p.fill('[name=cc5]', 'first\nsecond');
  await clickAndSettle(p, 'form[name=ccenter] input[type=submit]');
  await clickAndSettle(p, 'form[name=order] input[type=submit]:not([name=edit])');   // this form redirects to /thanks.php
  const id = await eventually(() => h.sql('SELECT max(msgid) FROM icms_ccenter_message WHERE fidref=50 AND msgid<>500'));
  const body = J(h.sql(`SELECT body FROM icms_ccenter_message WHERE msgid=${id}`));
  const f = Object.fromEntries(body.fields.map((x) => [x.key, x]));
  assert.equal(f['Kind'].value, 'a');
  assert.equal(f['Kind'].display, 'Question');
  assert.deepEqual(f['Topics'].value, ['f', 'g']);
  assert.equal(f['Topics'].display, 'Forum, Gallery');
  assert.equal(f['Message'].value, 'first\r\nsecond');   // exactly as the browser sent it
  assert.ok(!body.fields.some((x) => x.legacy), 'new messages are not marked legacy');
  const onepass = h.sql(`SELECT onepass FROM icms_ccenter_message WHERE msgid=${id}`);
  await p.goto(`${MOD}/message.php?id=${id}&p=${onepass}`);
  const t = await text(p);
  assert.match(t, /Question/);
  assert.match(t, /Forum, Gallery/);
  await ctx.close();
});

test('a renamed label does not change what an old message shows', async () => {
  const id = 500;   // converted from the earlier format with the definition of its form
  const defs = J(h.sql('SELECT defs FROM icms_ccenter_form WHERE formid=50'));
  defs.items.find((i) => i.key === 'Kind').label = 'Category';
  h.sql(`UPDATE icms_ccenter_form SET defs=${q(JSON.stringify(defs))} WHERE formid=50`);
  const p = await page(admin);
  await p.goto(`${MOD}/message.php?id=${id}`);
  const t = await text(p);
  assert.match(t, /Kind\s+Complaint/, 'the label of the time of sending');
  assert.doesNotMatch(t, /Category\s+Complaint/);
});

test('the form administration stores the definition as JSON and shows it as editable lines', async () => {
  const p = await page(admin);
  await p.goto(`${MOD}/admin/index.php?op=edit`);
  await p.fill('[name=title]', 'Admin made');
  await p.fill('[name=defs]', 'Subject*,size=30\nColor,select,r=Red,g+=Green\n# note');
  await clickAndSettle(p, '[name=formdefs]');
  await assertOk(p, 'after save');
  await eventually(() => h.sql("SELECT count(*) FROM icms_ccenter_form WHERE title='Admin made'") === '1');
  const stored = h.sql("SELECT defs FROM icms_ccenter_form WHERE title='Admin made'");
  const schema = J(stored);
  assert.equal(schema.version, 1);
  assert.equal(schema.items[0].required, true);
  assert.deepEqual(schema.items[1].options, [{ value: 'r', label: 'Red' }, { value: 'g', label: 'Green', default: true }]);
  assert.deepEqual(schema.items[2], { type: 'comment', text: ' note' });
  assert.equal(h.sql("SELECT optvars FROM icms_ccenter_form WHERE title='Admin made'"), '{}');
  assert.match(h.sql("SELECT grpperm FROM icms_ccenter_form WHERE title='Admin made'"), /^\|(\d+\|)+$/);

  const id = h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Admin made'");
  await p.goto(`${MOD}/admin/index.php?formid=${id}`);
  await assertOk(p, 'edit');
  assert.equal(await p.inputValue('[name=defs]'), 'Subject*,size=30\nColor,select,r=Red,g+=Green\n# note');
});

test('an invalid definition is refused with a message and not stored', async () => {
  const p = await page(admin);
  await p.goto(`${MOD}/admin/index.php?op=edit`);
  await p.fill('[name=title]', 'Broken');
  await p.fill('[name=defs]', '{"version":1,"items":[{"key":"A"},{"key":"A"}]}');
  await clickAndSettle(p, '[name=formdefs]');
  assert.match(await text(p), /used twice/);
  assert.equal(h.sql("SELECT count(*) FROM icms_ccenter_form WHERE title='Broken'"), '0');
  h.clearPhpLog();   // the invalid text was shown again and logged as such on purpose
});

test('deleting a form also removes its messages, log lines, comments, subscriptions and files', async () => {
  const uploads = '/var/www/html/uploads/ccenter';
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,defs,grpperm,priuid) VALUES (60,1,'To remove','A','|2|',1)`);
  h.sql(`INSERT INTO icms_ccenter_message (msgid,fidref,ctime,mtime,atime,body,status) VALUES (600,60,1,1,1,'A: x','a')`);
  h.sql(`INSERT INTO icms_ccenter_log (ltime,fidref,midref,euid,comment) VALUES (1,60,600,1,'x')`);
  const mid = h.sql("SELECT mid FROM icms_modules WHERE dirname='ccenter'");
  h.sql(`INSERT INTO icms_xoopsnotifications (not_modid,not_itemid,not_category,not_event,not_uid,not_mode) VALUES (${mid},600,'message','comment',1,0)`);
  h.compose(['exec', '-T', 'web', 'sh', '-c', `mkdir -p ${uploads}/00600 && echo hi > ${uploads}/00600/a.txt && chmod -R 777 ${uploads}`]);

  const p = await page(admin);
  await p.goto(`${MOD}/admin/index.php?op=delete&formid=60`);
  await clickAndSettle(p, '.confirmMsg input[type=submit]');
  await eventually(() => h.sql('SELECT count(*) FROM icms_ccenter_form WHERE formid=60') === '0');
  assert.equal(h.sql('SELECT count(*) FROM icms_ccenter_message WHERE fidref=60'), '0');
  assert.equal(h.sql('SELECT count(*) FROM icms_ccenter_log WHERE fidref=60'), '0');
  assert.equal(h.sql(`SELECT count(*) FROM icms_xoopsnotifications WHERE not_modid=${mid} AND not_itemid=600`), '0');
  assert.equal(h.compose(['exec', '-T', 'web', 'sh', '-c', `test -e ${uploads}/00600 && echo yes || echo no`]).trim(), 'no');
});

test('file download: only plain file names, no way out of the uploads folder', async () => {
  h.compose(['exec', '-T', 'web', 'sh', '-c', 'mkdir -p /var/www/html/uploads/ccenter/00500 && echo "<b>x</b>" > /var/www/html/uploads/ccenter/00500/note.html && chmod -R 777 /var/www/html/uploads/ccenter']);
  const guest = await browser.newContext();
  const p = await page(guest);
  const fetchFile = async (file, pass) => p.evaluate(async (u) => {
    const r = await fetch(u);
    return { status: r.status, type: r.headers.get('content-type'), disposition: r.headers.get('content-disposition'), body: (await r.text()).slice(0, 200) };
  }, `${MOD}/file.php?id=500&p=${pass}&file=${encodeURIComponent(file)}`);
  await p.goto(`${MOD}/index.php`);

  const ok = await fetchFile('note.html', 'legacy01');
  assert.match(ok.body, /<b>x<\/b>/);
  assert.match(ok.disposition, /^attachment/, 'html is downloaded, not shown');
  assert.equal(ok.type, 'application/octet-stream');

  for (const evil of ['../../../mainfile.php', '..%2F..%2Fmainfile.php', '/etc/passwd', '..\\..\\mainfile.php', '../../../../../../etc/passwd']) {
    const r = await fetchFile(evil, 'legacy01');
    assert.doesNotMatch(r.body, /XOOPS_DB_PASS|root:|<\?php/, `must not leak with ${evil}`);
  }
  const noPass = await fetchFile('note.html', 'wrong');
  assert.doesNotMatch(noPass.body, /<b>x<\/b>/, 'access control still applies');
});

test('the module logs no PHP diagnostics during these tests', () => {
  assert.deepEqual(h.moduleLogLines(), []);
});
