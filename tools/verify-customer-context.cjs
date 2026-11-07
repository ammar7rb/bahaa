const {chromium}=require('C:/Users/Hosopa/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');const root=path.resolve(__dirname,'..'),out='E:/project/deliverables/customer-history-20261107/qa';fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true}),checks=[];try{for(const theme of ['light','dark'])for(const width of [1440,390]){
const page=await browser.newPage({viewport:{width,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.route('**/*',route=>{const u=new URL(route.request().url());let file;if(u.pathname.startsWith('/assets/'))file=root+'/public'+u.pathname;else{const name=u.pathname.includes('payment-reviews')?'reviews':u.pathname.includes('/payments')?'payments-overview':u.pathname.includes('/debts')?'debts-active':u.pathname.includes('/subscriptions')?'subscriptions-current':u.pathname.includes('/actions')?'subscriptions-actions':'list-overview';file=root+'/storage/app/customer-accounts-qa/'+name+'-'+theme+'.html';}if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({body:file.endsWith('.html')?fs.readFileSync(file,'utf8').replaceAll('http://localhost','http://sigma.test'):fs.readFileSync(file),contentType:file.endsWith('.html')?'text/html':file.endsWith('.css')?'text/css':file.endsWith('.js')?'application/javascript':undefined});return route.fulfill({status:404,body:''});});
for(const name of ['payments','debts','subscriptions','actions','payment-reviews']){
 await page.goto('http://sigma.test/admin/'+(name==='payment-reviews'?name:'customer/accounts/'+name)+'?customer_id=1');await page.evaluate(()=>document.fonts.ready);
 const nav=page.locator('.ca-profile-navigation');assert.equal(await nav.locator('a').count(),4);for(const link of await nav.locator('a').all()){const u=new URL(await link.getAttribute('href'));assert(u.pathname.includes('/view/')||u.searchParams.get('customer_id')==='1');}
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Page overflow');
 if(name==='subscriptions'){
  await page.locator('[data-ca-intent=renew_package]').click();const form=page.locator('#ca-subscription form');
  for(const [intent,target] of [['renew_package','2'],['upgrade','3'],['downgrade','1']]){await form.locator('[name=intent]').selectOption(intent);assert.equal(await form.locator('[name=package_id]').inputValue(),target);assert.equal(await form.locator('[name=operation]').inputValue(),'purchase');assert(!await form.locator('[type=submit]').isDisabled());}
  await page.screenshot({path:out+`/operation-${theme}-${width}.png`});await page.keyboard.press('Escape');await page.waitForTimeout(180);
 }
 if(name==='actions')assert((await page.locator('body').innerText()).includes('HISTORY-2018'));
 if(name==='payment-reviews'){assert(!(await page.locator('body').innerText()).includes('مراجعة موحدة'));assert.equal(await page.locator('input[name=customer_id]').inputValue(),'1');}
 await page.screenshot({path:out+`/${name}-${theme}-${width}.png`,fullPage:true});
 if(name==='payment-reviews'&&width===390){await page.locator('.ca-review-status').first().scrollIntoViewIfNeeded();const box=await page.locator('.ca-review-status').first().boundingBox();assert(box.x>=0&&box.x+box.width<=width,'Status not visible');await page.screenshot({path:out+`/review-status-${theme}-${width}.png`,fullPage:false});}
 checks.push({theme,width,name});
}
// The existing iframe load handler retains the same contextual menu after every navigation.
await page.goto('http://sigma.test/admin/customer/accounts/list?customer_id=1');await page.locator('[data-ca-profile]').first().click();let frame=page.frames().find(f=>f!==page.mainFrame());await page.frameLocator('#ca-profile-modal iframe').locator('.ca-profile-navigation').waitFor();
for(const target of ['payments','debts','subscriptions']){await frame.locator(`.ca-profile-navigation a[href*="/accounts/${target}"]`).click();await frame.waitForURL(u=>u.pathname.endsWith('/accounts/'+target)&&u.searchParams.get('customer_id')==='1');assert.equal(await frame.locator('.ca-profile-navigation a').count(),4);assert.equal(new URL(frame.url()).searchParams.get('customer_id'),'1');}
await page.keyboard.press('Escape');await page.waitForTimeout(180);assert.deepEqual(errors,[]);await page.close();}
fs.writeFileSync(out+'/checks.json',JSON.stringify(checks,null,2));console.log('Passed 20 views: persistent customer context, scoped review, explicit operations and iframe navigation.');}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
