// Drives the ImpressCMS 2.0.4 installation wizard non-interactively.
//   BASE_URL=http://localhost:8083 DB_HOST=db node install.js
const { chromium } = require('playwright');

const cfg = {
  base: process.env.BASE_URL || 'http://localhost:8083',
  dbHost: process.env.DB_HOST || 'db',
  dbUser: process.env.DB_USER || 'root',
  dbPass: process.env.DB_PASS || 'root',
  dbName: process.env.DB_NAME || 'icms',
  adminName: process.env.ADMIN_NAME || 'admin',
  adminPass: process.env.ADMIN_PASS || 'Admin-pass-123',
  adminMail: process.env.ADMIN_MAIL || 'admin@example.com',
  trust: process.env.TRUST_PATH || '/var/www/trust',
};

const byName = {
  ROOT_PATH: '/var/www/html', URL: cfg.base, TRUST_PATH: cfg.trust,
  DB_HOST: cfg.dbHost, DB_USER: cfg.dbUser, DB_PASS: cfg.dbPass, DB_NAME: cfg.dbName,
  DB_PCONNECT: null, DB_PREFIX: 'icms',
  adminname: cfg.adminName, adminlogin_name: cfg.adminName,
  adminpass: cfg.adminPass, adminpass2: cfg.adminPass, adminmail: cfg.adminMail,
};

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(cfg.base + '/install/index.php');
  await page.check('input[name=lang][value=english]');
  for (let step = 0; step < 40; step++) {
    const stepNo = (await page.title()).match(/\((\d+)\/12\)/)?.[1];
    const file = page.url().split('/').pop().split('?')[0];
    const fields = await page.$$eval('input:not([type=radio]):not([type=button]):not([type=submit]):not([type=hidden]),select,textarea',
      els => els.map(e => ({ n: e.name || e.id, t: e.type, v: e.value })));
    console.log(`[${step}] ${file} (${stepNo}/12)`, fields.map(f => `${f.n}=${f.v}`).join(' '));
    for (const f of fields) {
      if (Object.prototype.hasOwnProperty.call(byName, f.n) && byName[f.n] !== null) {
        await page.fill(`[name="${f.n}"]`, byName[f.n]);
      }
    }
    if (file === 'page_end.php' || stepNo === '12') break;
    const next = await page.$('button.next, #buttons button[type=submit]');
    if (!next) { console.log('no next button'); break; }
    await Promise.all([page.waitForLoadState('load'), next.click()]);
    const err = await page.$$eval('.errorMsg, .x2-error, .error', e => e.map(x => x.innerText.trim()).filter(Boolean)).catch(() => []);
    if (err.length) console.log('  ERRORS:', err.join(' | '));
  }
  console.log('FINAL', page.url());
  console.log((await page.innerText('body')).slice(0, 500).replace(/\n+/g, ' / '));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
