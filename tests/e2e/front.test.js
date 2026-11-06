// The list pages for visitors and staff: lists, paging, sorting, status choice, reception, hostile parameters.
//   NODE_PATH=$(npm root -g) BASE_URL=http://localhost:8083 COMPOSE_PROJECT=ccenter-83 node --test front.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const h = require('./helpers');

const MOD = h.cfg.base + '/modules/ccenter';
const ERR_TEXT = /This page cannot be displayed due to an internal error|Fatal error|Parse error|Warning:|Deprecated:|Notice:/;

let browser;
let admin;
let member;
let memberId;
const openPages = [];
const pageOf = async (ctx) => { const p = await ctx.newPage(); openPages.push(p); return p; };
const text = async (p) => (await p.innerText('body')).replace(/\s+/g, ' ');
async function loginContext(user) {
  const ctx = await browser.newContext();
  const p = await pageOf(ctx);
  await h.login(p, user, h.cfg.pass);
  await p.close();
  return ctx;
}
async function open(ctx, url) {
  const p = await pageOf(ctx);
  const t0 = Date.now();
  await p.goto(`${MOD}/${url}`);
  const body = await text(p);
  assert.ok(!ERR_TEXT.test(body), `no error on ${url}: ${body.slice(0, 200)}`);
  return { p, body, ms: Date.now() - t0 };
}
const count = (body) => Number(body.match(/Count: (\d+)/)[1]);

test.afterEach(async () => { while (openPages.length) await openPages.pop().close().catch(() => {}); });
test.before(async () => {
  browser = await chromium.launch();
  h.sql("DELETE FROM icms_ccenter_form WHERE title<>'Contact us'; TRUNCATE icms_ccenter_message; TRUNCATE icms_ccenter_log; TRUNCATE icms_protector_access");
  memberId = h.createUser('tester', [2]);
  member = await loginContext('tester');
  admin = await loginContext(h.cfg.admin);
  h.sql(`INSERT INTO icms_ccenter_form (formid,mtime,title,defs,grpperm,priuid,cgroup,optvars) VALUES
    (81,1,'Form <b>A</b>','Q*','|2|',${memberId},0,'{}'),
    (82,1,'Form B','Q*','|2|',0,2,'{}')`);
  // 25 messages of the member for form 81 (the member is the person in charge), 3 for form 82 (nobody in charge)
  const rows = [];
  for (let i = 1; i <= 25; i++) rows.push(`(${900 + i},${memberId},${memberId},1000,${2000 + i},1,81,'','${i % 5 === 0 ? 'b' : (i % 2 ? '-' : 'a')}')`);
  for (let i = 1; i <= 3; i++) rows.push(`(${950 + i},${memberId},0,1000,${3000 + i},1,82,'','-')`);
  h.sql(`INSERT INTO icms_ccenter_message (msgid,uid,touid,ctime,mtime,atime,fidref,email,status) VALUES ${rows.join(',')}`);
  h.clearPhpLog();
});
test.after(async () => { await browser.close(); });

test('"my messages" lists with paging, sorting and the status choice, and escapes the form title', async () => {
  const max = Number(h.sql("SELECT conf_value FROM icms_config WHERE conf_name='max_lists' AND conf_modid=(SELECT mid FROM icms_modules WHERE dirname='ccenter')") || 20);
  const { p, body } = await open(member, 'list.php?reset=yes&stat=-%20a%20b%20c');
  assert.equal(count(body), 28);
  assert.ok(await p.$('table.outer'), 'a table');
  assert.ok(!(await p.innerHTML('table.outer')).includes('<b>A</b>'), 'the form title is escaped');
  const rows = (await p.$$('table.outer tr')).length - 1;
  assert.equal(rows, Math.min(28, max));
  if (28 > max) {
    assert.ok((await p.$$('.ccpagenav a')).length > 0, 'paging links');
    const second = await open(member, 'list.php?start=' + max);
    assert.equal((await second.p.$$('table.outer tr')).length - 1, 28 - max);
  }
  const openOnes = await open(member, 'list.php?stat=-%20a');
  assert.ok(count(openOnes.body) < 28 && count(openOnes.body) > 0, 'only the open statuses');
  await open(member, 'list.php?mtime=desc');
});

test('"in my charge" shows only the messages of the person in charge; an ordinary member cannot choose another one', async () => {
  const mine = await open(member, 'charge.php?reset=yes&stat=-%20a%20b%20c');
  assert.equal(count(mine.body), 25);
  const other = await open(member, 'charge.php?touid=1');
  assert.equal(count(other.body), 25, 'touid is ignored for members');
  const adm = await open(admin, `charge.php?touid=${memberId}&reset=yes&stat=-%20a%20b%20c`);
  assert.equal(count(adm.body), 25, 'an administrator can look at another person');
});

test('reception: all forms for an administrator, only own forms for a contact person', async () => {
  const adm = await open(admin, 'reception.php');
  assert.match(adm.body, /Form B/);
  const mem = await open(member, 'reception.php');
  assert.match(mem.body, /Form B/, 'the contact group of form B includes the member');
  const one = await open(admin, 'reception.php?form=81');
  assert.ok(one.body.length > 0);
  const guest = await browser.newContext();
  const g = await pageOf(guest);
  await g.goto(`${MOD}/reception.php`);
  assert.match(await text(g), /not have permission|Login|Username/i, 'guests are sent away');
});

test('hostile list parameters change nothing and do not delay the page', async () => {
  const evil = [
    'list.php?form=1%20OR%20SLEEP(3)',
    'list.php?start=1;SELECT%20SLEEP(3)',
    'list.php?stat=%27%20OR%20SLEEP(3)%20OR%20%27',
    'list.php?mtime=asc,(SELECT%20SLEEP(3))',
    'list.php?orders[]=sleep(3)',
    'charge.php?touid=1%20OR%20SLEEP(3)',
    'charge.php?form=0;SLEEP(3)',
    'reception.php?form=1%20OR%20SLEEP(3)',
  ];
  for (const u of evil) {
    const ctx = u.startsWith('reception') ? admin : member;
    const { ms } = await open(ctx, u);
    assert.ok(ms < 2500, `${u} took ${ms} ms`);
  }
});

test('no PHP diagnostics were logged by module code', async () => {
  const lines = h.moduleLogLines();
  assert.deepEqual(lines, [], lines.join('\n'));
});
