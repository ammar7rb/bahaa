import fs from 'node:fs';
import path from 'node:path';
import cp from 'node:child_process';
import {createHash} from 'node:crypto';
const root = 'E:/xampp/htdocs/ba';
const out = 'E:/project/deliverables/customer-packages';
const files = new Set();
const add = file => { if (!fs.existsSync(path.join(root,file))) throw new Error(`Missing ${file}`); files.add(file); };
const walk = dir => { for (const entry of fs.readdirSync(path.join(root,dir),{withFileTypes:true})) {
  const file = `${dir}/${entry.name}`; if (entry.isDirectory()) walk(file); else add(file);
}};
for (const dir of ['resources/views/customer-subscription','resources/views/customer-account','resources/views/customer-support',
  'resources/views/customer-checkout','resources/views/web-views/users-profile/partials']) walk(dir);
for (const file of fs.readdirSync(path.join(root,'app/Services'))) if (/^Customer.*\.php$/.test(file)) add(`app/Services/${file}`);
for (const file of [
 'app/Http/Controllers/Customer/AccountDeletionController.php','app/Http/Controllers/Customer/ProfileImageController.php',
 'app/Http/Controllers/Customer/PurchasingLimitController.php','app/Http/Controllers/Customer/SupportConversationController.php',
 'app/Http/Controllers/Customer/MonthlySubscriptionController.php','app/Http/Controllers/Customer/Auth/CheckoutRegistrationController.php',
 'app/Http/Controllers/Admin/Customer/MonthlyPackageController.php','app/Http/Controllers/Admin/Customer/CustomerPurchasePackageController.php',
 'app/Http/Controllers/Admin/Customer/CustomerController.php','app/Http/Controllers/Admin/Customer/MonthlySubscriptionController.php',
 'app/Http/Controllers/Admin/PaymentReviewController.php','app/Http/Controllers/RestAPI/v1/BannerController.php',
 'app/Http/Controllers/RestAPI/v1/CustomerController.php','app/Http/Controllers/RestAPI/v1/OrderController.php',
 'app/Http/Controllers/RestAPI/v1/NotificationController.php','app/Http/Controllers/Web/UserProfileController.php',
 'app/Services/AccountActivationCenterService.php','app/Services/AdminWorkspaceService.php',
 'app/Http/Middleware/EndDeletedCustomerSession.php','app/Http/Middleware/APIGuestMiddleware.php',
 'app/Http/Middleware/Authenticate.php','app/Http/Middleware/CustomerMiddleware.php',
 'app/Models/CustomerMonthlySubscription.php','app/Models/CustomerSubscriptionPayment.php','app/Models/Notification.php',
 'app/Utils/OrderManager.php','bootstrap/app.php','routes/web/routes.php','routes/rest_api/v1/api.php','routes/admin/routes.php',
 'resources/views/admin-views/customer/monthly-packages.blade.php','resources/views/admin-views/customer/customer-view.blade.php',
 'resources/views/admin-views/customer/purchase-state.blade.php','resources/views/admin-views/customer/subscriptions.blade.php',
 'resources/views/layouts/admin/partials/_side-bar.blade.php','resources/views/layouts/admin/partials/v2/_side-bar.blade.php',
 'resources/themes/default/layouts/front-end/app.blade.php','resources/themes/theme_aster/theme-views/layouts/app.blade.php',
 'resources/themes/theme_sigma_care/theme-views/layouts/app.blade.php',
 'resources/themes/default/web-views/cart/_cart-details.blade.php','resources/themes/default/web-views/cart/cart-list.blade.php',
 'resources/themes/theme_aster/theme-views/cart/cart-details.blade.php','resources/themes/theme_aster/theme-views/cart/cart-list.blade.php',
 'resources/themes/theme_aster/theme-views/checkout/payment.blade.php',
 'resources/themes/default/web-views/partials/_profile-aside.blade.php',
 'resources/themes/default/web-views/users-profile/user-account.blade.php',
 'resources/themes/theme_aster/theme-views/users-profile/profile/user-profile.blade.php',
 'resources/themes/theme_sigma_care/theme-views/users-profile/profile/user-profile.blade.php',
 'resources/themes/theme_sigma_care/theme-views/partials/_care-support-panel.blade.php',
 'public/assets/front-end/css/customer-packages.css','public/assets/front-end/css/customer-subscription.css',
 'public/assets/front-end/js/customer-packages.js','public/assets/front-end/js/customer-purchasing-limit.js',
 'public/assets/front-end/fonts/brand/vazirmatn.ttf'
]) add(file);
for (const file of fs.readdirSync(path.join(root,'database/migrations'))) if (/^2026_10_12_1[0-6]0000_/.test(file)) add(`database/migrations/${file}`);
const env = {...process.env,GIT_INDEX_FILE:`${out}/monthly-release.index`};
const git = (...args) => cp.execFileSync('git',args,{cwd:root,env,encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const parent = git('rev-parse','codex/customer-vps-release');
git('read-tree',parent);git('add','-f','--',...files);
for (const file of files) {
  const merged = `${out}/server-merged/${file}`;
  if (!fs.existsSync(merged)) continue;
  const content = fs.readFileSync(merged);
  if (/^<<<<<<< |^>>>>>>> /m.test(content.toString())) throw new Error(`Unresolved conflict: ${file}`);
  const hash = cp.execFileSync('git',['hash-object','-w','--stdin'],{cwd:root,env,input:content,encoding:'utf8'}).trim();
  git('update-index','--add','--cacheinfo',`100644,${hash},${file}`);
}
const changed = git('diff','--cached',parent,'--name-only').split('\n').filter(Boolean);
if (!changed.length) throw new Error('Empty release');
if (changed.some(file=>!files.has(file))) throw new Error('Out-of-scope staged file');
git('-c','core.whitespace=-blank-at-eol,-blank-at-eof','diff','--cached',parent,'--check');
for (const file of changed) if (file.endsWith('.php') && !file.includes('.blade.')) cp.execFileSync('E:/xampp/php/php.exe',['-l',path.join(root,file)],{stdio:'pipe'});
const tree = git('write-tree');
const commit = git('commit-tree',tree,'-p',parent,'-m','Customer monthly packages, allowance safeguards and refined Arabic selection');
git('archive','--format=tar',`--output=${out}/monthly-release.tar`,commit,'--',...changed);
const baseHashes = Object.fromEntries(changed.map(file => {
  let hash = null;
  try { hash = createHash('sha256').update(cp.execFileSync('git',['show',`${parent}:${file}`],{cwd:root,stdio:['ignore','pipe','pipe']})).digest('hex'); } catch {}
  const server = `${out}/server-existing/${file}`;
  if (fs.existsSync(server)) hash = createHash('sha256').update(fs.readFileSync(server)).digest('hex');
  return [file,hash];
}));
const manifest = {parent,commit,files:changed,baseHashes};
fs.writeFileSync(`${out}/monthly-release-manifest.json`,JSON.stringify(manifest,null,2));
fs.writeFileSync(`${out}/monthly-release-files.txt`,changed.join('\n')+'\n');
console.log(JSON.stringify({parent,commit,count:changed.length,files:changed},null,2));
