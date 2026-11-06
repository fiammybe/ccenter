// Upgrade test, in two steps around a switch of the module code (see tests/upgrade-test.sh):
//   seed   - with the OLD code: create a form and messages, remember what the pages show
//   verify - with the NEW code: run the module update, check that everything is converted and shows the same
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const h = require('./helpers');

const MOD = h.cfg.base + '/modules/ccenter';
const STATE = path.resolve(__dirname, '..', '.work', 'upgrade-state.json');
const DEFS = [
  '# Please fill in',
  'Your name*,size=40',
  'Email*,mail',
  'Kind*,radio,q=Question,c+=Complaint,o=Other',
  'Topics,checkbox,n=News,f=Forum,g=Gallery',
  'Colour,select,r=Red,g=Green',
  'Message,textarea,rows=4,#Be brief',
].join('\n');

const norm = (t) => t.replace(/\s+/g, ' ');

async function fieldsOf(page, id, pass) {
  await page.goto(`${MOD}/message.php?id=${id}${pass ? '&p=' + pass : ''}`);
  const t = norm(await page.innerText('body'));
  const from = t.indexOf('Charge');
  // The earlier version showed the "required" mark behind the labels of a submitted message; the stored
  // label has none (a deliberate change), so the mark is left out of the comparison.
  return norm(t.slice(from, t.indexOf('Flat', from)).replace(/\*/g, ''));
}

async function seed() {
  const browser = await chromium.launch();
  const admin = await browser.newContext();
  const p = await admin.newPage();
  h.sql('TRUNCATE icms_protector_access');
  await h.login(p);

  await p.goto(`${MOD}/admin/index.php?op=edit`);
  if (!(await p.$('[name=title]'))) throw new Error('admin form missing: ' + norm(await p.innerText('body')).slice(0, 500));
  await p.fill('[name=title]', 'Upgrade form');
  await p.fill('[name=defs]', DEFS);
  await h.clickAndSettle(p, '[name=formdefs]');
  const formId = await h.eventually(() => h.sql("SELECT formid FROM icms_ccenter_form WHERE title='Upgrade form'"));

  h.sql(`UPDATE icms_ccenter_form SET grpperm='|1|2|3|' WHERE formid=${formId}`);   // open to guests as well

  const sent = [];
  for (const who of ['Alan Turing', 'Grace Hopper']) {
    const g = await (await browser.newContext()).newPage();
    await g.goto(`${MOD}/index.php?form=${formId}`);
    await g.fill('[name=cc1]', who);
    await g.fill('[name=cc2]', 'x@example.com');
    await g.fill('[name=cc2_conf]', 'x@example.com');
    await g.check('[name=cc3][value=c]');
    await g.check('[name=cc4\\[\\]][value=n]');
    await g.check('[name=cc4\\[\\]][value=g]');
    await g.selectOption('[name=cc5]', 'g');
    await g.fill('[name=cc6]', `line one of ${who}\nline two`);
    await h.clickAndSettle(g, 'form[name=ccenter] input[type=submit]');
    await Promise.all([g.waitForURL(/message\.php\?id=\d+/, { timeout: 20000 }), g.click('form[name=order] input[type=submit]:not([name=edit])')]);
    const u = new URL(g.url());
    sent.push({ id: u.searchParams.get('id'), pass: u.searchParams.get('p'), who });
    await g.context().close();
  }
  const before = {};
  for (const m of sent) before[m.id] = await fieldsOf(p, m.id, null);
  const csv = await p.evaluate(async (u) => (await fetch(u)).text(), `${MOD}/export.php?form=${formId}&range=all`);
  fs.mkdirSync(path.dirname(STATE), { recursive: true });
  fs.writeFileSync(STATE, JSON.stringify({ formId, sent, before, csv, oldBody: h.sql(`SELECT body FROM icms_ccenter_message WHERE msgid=${sent[0].id}`), oldDefs: h.sql(`SELECT defs FROM icms_ccenter_form WHERE formid=${formId}`) }, null, 1));
  console.log('seeded', sent.map((m) => m.id).join(','), '\nold body:\n' + h.sql(`SELECT body FROM icms_ccenter_message WHERE msgid=${sent[0].id}`));
  await browser.close();
}

async function verify() {
  const st = JSON.parse(fs.readFileSync(STATE, 'utf8'));
  const browser = await chromium.launch();
  const admin = await browser.newContext();
  const p = await admin.newPage();
  h.sql('TRUNCATE icms_protector_access');
  await h.login(p);

  assert.doesNotMatch(st.oldBody, /^\s*\{/, 'the old version stored the old text format');
  assert.doesNotMatch(st.oldDefs, /^\s*\{/);

  // before the update the new code reads the old data
  for (const m of st.sent) assert.equal(await fieldsOf(p, m.id, null), st.before[m.id], `message ${m.id} before the update`);

  const out = norm(await h.updateModule(p));
  assert.doesNotMatch(out, /internal error|Fatal|Warning:/, out.slice(0, 400));
  assert.match(out, /Converted/);

  for (const m of st.sent) {
    assert.equal(await fieldsOf(p, m.id, null), st.before[m.id], `message ${m.id} after the update`);
    const body = JSON.parse(h.sql(`SELECT body FROM icms_ccenter_message WHERE msgid=${m.id}`));
    assert.equal(body.version, 1);
    const f = Object.fromEntries(body.fields.map((x) => [x.key, x]));
    assert.equal(f['Your name'].value, m.who);
    assert.equal(f['Kind'].display, 'Complaint');
    assert.equal(f['Colour'].display, 'Green');
    assert.match(f['Message'].value, /line two$/);
    assert.equal(Array.isArray(f['Topics'].value), true);
    // the guest can still open it with the one-time password
    const g = await (await browser.newContext()).newPage();
    const guestView = await fieldsOf(g, m.id, m.pass);
    assert.match(guestView, new RegExp(m.who));
    await g.context().close();
  }
  const defs = JSON.parse(h.sql(`SELECT defs FROM icms_ccenter_form WHERE formid=${st.formId}`));
  assert.equal(defs.items[0].type, 'comment');
  assert.deepEqual(defs.items.filter((i) => i.type !== 'comment').map((i) => i.key), ['Your name', 'Email', 'Kind', 'Topics', 'Colour', 'Message']);
  assert.equal(h.sql(`SELECT group_concat(gperm_groupid ORDER BY gperm_groupid) FROM icms_group_permission WHERE gperm_name='form_view' AND gperm_itemid=${st.formId}`), '1,2,3', 'the groups became group permissions');
  assert.equal(h.sql(`SELECT grpperm FROM icms_ccenter_form WHERE formid=${st.formId}`), '|1|2|3|', 'the old text is kept');
  const prefs = h.sql("SELECT conf_value FROM icms_config WHERE conf_name='def_attrs'").replace(/<!--.*?-->/g, '');
  JSON.parse(prefs);

  const csv = await p.evaluate(async (u) => (await fetch(u)).text(), `${MOD}/export.php?form=${st.formId}&range=all`);
  for (const m of st.sent) assert.ok(csv.includes(m.who), `${m.who} is in the export`);
  assert.deepEqual(h.moduleLogLines(), [], 'no PHP diagnostics from the module');

  // repeating the update changes nothing
  const snap = h.sql('SELECT md5(group_concat(body)) FROM icms_ccenter_message');
  await h.updateModule(p);
  assert.equal(h.sql('SELECT md5(group_concat(body)) FROM icms_ccenter_message'), snap);
  console.log('upgrade verified for messages', st.sent.map((m) => m.id).join(','));
  await browser.close();
}

(async () => {
  const step = process.argv[2];
  if (step === 'seed') await seed();
  else if (step === 'verify') await verify();
  else throw new Error('usage: upgrade.js seed|verify');
})().catch((e) => { console.error(e); process.exit(1); });
