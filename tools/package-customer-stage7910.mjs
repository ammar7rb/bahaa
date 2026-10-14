import fs from 'node:fs';
import cp from 'node:child_process';
import {createHash} from 'node:crypto';
const out='E:/project/deliverables/customer-stage7910-20261014';
fs.mkdirSync(out,{recursive:true});
const backendFiles=[
'app/Services/CustomerAccountDeletionService.php','app/Http/Controllers/Customer/AccountDeletionController.php',
'routes/web/routes.php','routes/rest_api/v1/api.php','resources/views/customer-account/delete.blade.php',
'resources/themes/theme_sigma_care/theme-views/layouts/partials/_header.blade.php',
'resources/themes/theme_sigma_care/theme-views/pages/business_page.blade.php',
'database/migrations/2026_10_14_200000_add_veterinary_services_business_page.php'];
const backendTests=['tests/Unit/CustomerAccountDeletionTest.php','tests/Unit/VeterinaryServicesPageTest.php',
 'tests/Unit/CustomerPasswordAndTrackingTest.php','tests/Unit/CustomerSubscriptionBannerTest.php','tests/Unit/IdentityAndSupportBatchTest.php'];
const normalize=b=>Buffer.from(b.toString('utf8').replace(/^\uFEFF/,'').replace(/\r\n/g,'\n'));
const sha=b=>createHash('sha256').update(b).digest('hex');
for(const mode of ['backend','mobile']){
 const root=mode==='backend'?'E:/xampp/htdocs/ba':'E:/project/user-app-main';
 const env={...process.env,GIT_INDEX_FILE:`${out}/${mode}.index`};
 const git=(...args)=>cp.execFileSync('git',args,{cwd:root,env,encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
 const parent=mode==='backend'?'ecc729aad59ccc915b41fda00cf331980dd9145c':git('rev-parse','origin/main');
 const files=mode==='backend'?[...backendFiles,...backendTests]:['lib/features/profile/widgets/account_deletion_sheet.dart','test/customer_deletion_confirmation_test.dart'];
 git('read-tree',parent);
 const hashes={},baseHashes={};
 for(const file of files){
  const source=normalize(fs.readFileSync(`${root}/${file}`));
  let old=null;try{old=normalize(cp.execFileSync('git',['show',`${parent}:${file}`],{cwd:root,stdio:['ignore','pipe','ignore']}));}catch{}
  const liveOverride=mode==='backend'?{
   'resources/themes/theme_sigma_care/theme-views/layouts/partials/_header.blade.php':'header.blade.php',
   'resources/themes/theme_sigma_care/theme-views/pages/business_page.blade.php':'business_page.blade.php'
  }[file]:null;
  if(liveOverride)old=normalize(fs.readFileSync(`${out}/live-before/${liveOverride}`));
  hashes[file]=sha(source);baseHashes[file]=old?sha(old):null;
  const blob=cp.execFileSync('git',['hash-object','-w','--stdin'],{cwd:root,env,input:source,encoding:'utf8'}).trim();
  git('update-index','--add','--cacheinfo',`100644,${blob},${file}`);
 }
 const branch='codex/customer-stages7910';
 const commit=git('commit-tree',git('write-tree'),'-p',parent,'-m','Add veterinary header page and single account deletion confirmation; verify customer flows');
 git('update-ref',`refs/heads/${branch}`,commit);
 const manifest={mode,parent,commit,branch,files,deployFiles:mode==='backend'?backendFiles:files,hashes,baseHashes};
 fs.writeFileSync(`${out}/${mode}-manifest.json`,JSON.stringify(manifest,null,2));
 fs.writeFileSync(`${out}/${mode}-review.diff`,cp.execFileSync('git',['diff',parent,commit],{cwd:root}));
 git('archive','--format=tar',`--output=${out}/${mode}.tar`,commit,'--',...files);
 console.log(JSON.stringify({mode,parent,commit,files:files.length}));
}
