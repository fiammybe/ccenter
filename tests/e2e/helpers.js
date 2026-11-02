// Shared helpers for the ccenter smoke tests.
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const cfg = {
  base: process.env.BASE_URL || 'http://localhost:8083',
  project: process.env.COMPOSE_PROJECT || 'ccenter-83',
  admin: process.env.ADMIN_NAME || 'admin',
  pass: process.env.ADMIN_PASS || 'Admin-pass-123',
  composeDir: path.resolve(__dirname, '..'),
};

function compose(args, input) {
  return execFileSync('docker', ['compose', '-p', cfg.project, ...args],
    { cwd: cfg.composeDir, encoding: 'utf8', input, stdio: ['pipe', 'pipe', 'pipe'] });
}

const phpLog = () => compose(['exec', '-T', 'web', 'cat', '/var/log/php_errors.log']);
const clearPhpLog = () => compose(['exec', '-T', 'web', 'sh', '-c', ': > /var/log/php_errors.log']);
const dropAdminMenuCache = () => compose(['exec', '-T', 'web', 'sh', '-c', 'rm -f /var/www/html/cache/adminmenu_*.php']);
const sql = (q) => compose(['exec', '-T', 'db', 'mariadb', '-uroot', '-proot', 'icms', '-N', '-B', '-e', q]).trim();

// Log lines that point at module code or at its compiled templates (core/Protector noise is ignored on purpose).
function moduleLogLines() {
  return phpLog().split('\n').filter((l) => l.includes('/modules/ccenter/') || /db%3Accenter_|\/ccenter_[a-z_]+\.html/.test(l));
}

async function login(page, user = cfg.admin, pass = cfg.pass) {
  await page.goto(cfg.base + '/user.php');
  await page.fill('input[name=uname]', user);
  await page.fill('input[name=pass]', pass);
  await Promise.all([page.waitForLoadState('load'), page.click('input[type=submit][value*=ogin], button[type=submit]')]);
}

// Run the module update from the ImpressCMS module administration; returns the page text.
// (Template files are stored in the database, so changed templates only take effect after an update.)
async function updateModule(page, dirname = 'ccenter') {
  await page.goto(`${cfg.base}/modules/system/admin.php?fct=modulesadmin&op=update&module=${dirname}`);
  await Promise.all([page.waitForLoadState('load'), page.click('input[name=confirm_submit]')]);
  return (await page.innerText('body')).replace(/\s+/g, ' ');
}

module.exports = { updateModule, cfg, compose, phpLog, clearPhpLog, dropAdminMenuCache, sql, moduleLogLines, login };
