// Run with Playwright installed, or set SIGMA_PLAYWRIGHT_MODULE to its module path.
const { chromium } = require(process.env.SIGMA_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'public/assets/back-end/js/vendor/product-add-update.js'), 'utf8');
const handler = source.slice(source.indexOf('let vendorProductSubmissionPending'), source.indexOf('$(".action-onclick-generate-number")'));
const jquery = fs.readFileSync(path.join(root, 'public/assets/back-end/vendor/jquery/jquery.min.js'), 'utf8');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const mode of ['saved', 'validation-only', 'retry', 'cancel']) {
      const page = await browser.newPage();
      let posts = 0;
      await page.route('https://vendor-regression.test/**', async route => {
        const request = route.request();
        if (request.method() === 'POST') {
          posts++;
          assert.match(request.postData(), /name="publish_product"\r\n\r\n1/);
          return route.fulfill({ status: mode === 'retry' && posts === 1 ? 422 : 200,
            contentType: 'application/json', body: JSON.stringify(mode === 'validation-only' ? {} : {
              saved: true, redirect_url: 'https://vendor-regression.test/done' }) });
        }
        return route.fulfill({ contentType: 'text/html', body: '<html><body>Isolated product submission</body></html>' });
      });
      await page.goto('https://vendor-regression.test/form');
      await page.setContent('<meta name="csrf-token" content="test"><form id="product_form" action="/products/add"><input name="publish_product" value="1"><input id="discount" value="0"><button type="button" class="product-add-requirements-check">Save</button></form><div id="loading"></div>');
      await page.addScriptTag({ content: jquery });
      await page.evaluate(() => {
        sessionStorage.setItem('nativeSubmits', '0');
        window.nextConfirmation = true;
        window.Swal = { fire: async () => ({ value: window.nextConfirmation }) };
        window.validateFormHelper = async () => true;
        window.formSubmitCleanup = () => {};
        window.toastMagic = { error: () => {} };
        for (const name of ['messageAreYouSure', 'messageWantAddOrUpdateThisProduct', 'messageNoWord', 'messageYesWord']) window[name] = 'test';
        $('#product_form').on('submit', event => { event.preventDefault(); sessionStorage.setItem('nativeSubmits', String(Number(sessionStorage.getItem('nativeSubmits')) + 1)); });
      });
      await page.addScriptTag({ content: handler });
      if (mode === 'cancel') {
        await page.evaluate(() => { window.nextConfirmation = false; });
        await page.click('button');
        await page.waitForTimeout(100);
        assert.equal(posts, 0);
        await page.evaluate(() => { window.nextConfirmation = true; });
      }
      if (mode === 'retry') {
        await page.click('button');
        await page.waitForFunction(() => !vendorProductSubmissionPending);
        assert.equal(posts, 1);
      }
      if (mode === 'saved') await page.evaluate(() => { document.querySelector('button').click(); document.querySelector('button').click(); });
      else await page.click('button');
      if (mode === 'validation-only') await page.waitForFunction(() => sessionStorage.getItem('nativeSubmits') === '1');
      else await page.waitForURL('**/done');
      assert.equal(posts, mode === 'retry' ? 2 : 1);
      assert.equal(await page.evaluate(() => Number(sessionStorage.getItem('nativeSubmits'))), mode === 'validation-only' ? 1 : 0);
      console.log('PASS product submission: ' + mode);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
