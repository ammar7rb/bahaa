// Run with a local Playwright install; no form is submitted to production.
const { chromium } = require(process.env.SIGMA_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const scripts = {
  '/intl.js': 'public/assets/backend/libs/intl-tel-input/js/intlTelInput.js',
  '/utils.js': 'public/assets/backend/libs/intl-tel-input/js/utils.js',
  '/legacy.js': 'public/assets/backend/libs/intl-tel-input/js/intlTelInout-validation.js',
  '/egypt.js': 'public/assets/front-end/js/egypt-phone-validation.js',
};
const controls = {
  'checkout-register': 'resources/views/customer-checkout/register.blade.php',
  'sign-up': 'resources/views/customer-account/registration-fields.blade.php',
  profile: 'resources/themes/theme_sigma_care/theme-views/users-profile/profile/update-user-profile.blade.php',
  checkout: 'resources/themes/theme_aster/theme-views/checkout/shipping.blade.php',
};
const results = [];
function fixture(name) {
  const source = fs.readFileSync(path.join(root, controls[name]), 'utf8').replace(/\{\{[\s\S]*?\}\}/g, '');
  const input = source.match(/<input\b[^>]*type=["']tel["'][^>]*>/);
  assert(input, name + ': actual template phone input is required');
  return '<!doctype html><html lang="ar"><body><form>' + input[0] +
    '<button type="button">خارج الحقل</button></form>' +
    Object.keys(scripts).map(src => '<script src="' + src + '"></script>').join('') + '</body></html>';
}
async function check(page, label) {
  const input = page.locator('input[type="tel"]:visible').first();
  await input.fill('');
  let typed = '';
  for (const digit of '01000000003') {
    typed += digit;
    await input.pressSequentially(digit);
    assert.equal(await input.inputValue(), typed, label + ': value after each keystroke');
  }
  await input.press('Tab');
  assert.equal(await input.inputValue(), '01000000003', label + ': blur');
  const submitted = await input.evaluate(el => new FormData(el.form).get(el.name));
  assert.equal(submitted, '01000000003', label + ': submitted form value');
  assert.equal(await input.evaluate(el => el.closest('.iti') !== null), false, label + ': no legacy formatter');
  if (label.startsWith('template/')) {
    for (const pasted of ['+201000000003', '00201000000003', '٠١٠٠٠٠٠٠٠٠٣']) {
      await input.fill(pasted);
      await input.press('Tab');
      assert.equal(await input.inputValue(), '01000000003', label + ': pasted local/international/Arabic digits');
    }
  }
  results.push({ label, typed: '01000000003', submitted, passed: true });
}
(async () => {
  const server = http.createServer((req, res) => {
    if (scripts[req.url]) { res.setHeader('Content-Type', 'application/javascript'); return res.end(fs.readFileSync(path.join(root, scripts[req.url]))); }
    const name = req.url.slice(1);
    if (!controls[name]) { res.statusCode = 404; return res.end(); }
    res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end(fixture(name));
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch({ channel: process.env.SIGMA_BROWSER_CHANNEL || 'chrome', headless: true });
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      for (const name of Object.keys(controls)) {
        await page.goto('http://127.0.0.1:' + server.address().port + '/' + name);
        await check(page, 'template/' + name + '/' + width);
      }
      await page.evaluate(() => {
        document.querySelector('input[type="tel"]').remove();
        const input = document.createElement('input');
        input.type = 'tel'; input.name = 'sender_phone';
        document.querySelector('form').prepend(input);
      });
      await check(page, 'template/dynamic-checkout/' + width);
      await page.close();
    }
    if (process.env.SIGMA_TEST_LIVE_PHONE === '1') {
      const page = await browser.newPage();
      await page.route('**/intlTelInout-validation.js*', route => route.fulfill({ path: path.join(root, scripts['/legacy.js']), contentType: 'application/javascript' }));
      await page.route('**/egypt-phone-validation.js*', route => route.fulfill({ path: path.join(root, scripts['/egypt.js']), contentType: 'application/javascript' }));
      for (const url of ['https://sigma-eg.net/customer/auth/checkout-register', 'https://sigma-eg.net/customer/auth/sign-up']) {
        await page.goto(url, { waitUntil: 'networkidle' });
        const modal = page.locator('#registerModal');
        if (await modal.count() && !(await page.locator('input[type="tel"]:visible').count())) {
          await page.evaluate(() => window.bootstrap.Modal.getOrCreateInstance(document.getElementById('registerModal')).show());
        }
        await check(page, 'live-html-with-local-script/' + url);
      }
      await page.close();
    }
    const report = JSON.stringify({ browser: await browser.version(), results }, null, 2);
    if (process.env.SIGMA_BROWSER_REPORT) fs.writeFileSync(process.env.SIGMA_BROWSER_REPORT, report + '\n');
    console.log(report);
  } finally { await browser.close(); server.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
