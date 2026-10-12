import fs from 'node:fs';
import cp from 'node:child_process';
import {createHash} from 'node:crypto';
const root='E:/xampp/htdocs/ba',out='E:/project/deliverables/customer-packages';
const env={...process.env,GIT_INDEX_FILE:`${out}/profile-payment.index`};
const git=(...args)=>cp.execFileSync('git',args,{cwd:root,env,encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const parent=git('rev-parse','codex/customer-vps-release');
const layouts=['resources/themes/default/layouts/front-end/app.blade.php','resources/themes/theme_aster/theme-views/layouts/app.blade.php','resources/themes/theme_sigma_care/theme-views/layouts/app.blade.php'];
const files=[...layouts,'resources/views/customer-subscription/profile-overview.blade.php','resources/views/customer-subscription/payment-dialogs.blade.php','resources/views/customer-subscription/icon.blade.php','resources/views/customer-subscription/packages.blade.php','public/assets/front-end/css/customer-profile-overview.css','public/assets/front-end/css/customer-transfer.css','resources/themes/theme_sigma_care/theme-views/users-profile/profile/user-profile.blade.php'];
git('read-tree',parent);git('add','-f','--',...files);
for(const file of layouts){
 const old=cp.execFileSync('git',['show',`${parent}:${file}`],{cwd:root,encoding:'utf8'});
 const next=old.replace(/@if\(request\(\)->routeIs\('user-profile','user-account'\)\)\r?\n@include\('web-views.users-profile.partials.customer-profile-shortcuts'\)\r?\n@elseif\(/,"@if(");
 if(next===old)throw new Error(`Duplicate overview block not found: ${file}`);
 const hash=cp.execFileSync('git',['hash-object','-w','--stdin'],{cwd:root,env,input:next,encoding:'utf8'}).trim();
 git('update-index','--cacheinfo',`100644,${hash},${file}`);
}
const commit=git('commit-tree',git('write-tree'),'-p',parent,'-m','Refine original customer profile and repair package payment dialogs');
const baseHashes=Object.fromEntries(files.map(file=>[file,createHash('sha256').update(cp.execFileSync('git',['show',`${parent}:${file}`],{cwd:root})).digest('hex')]));
fs.writeFileSync(`${out}/profile-payment-manifest.json`,JSON.stringify({parent,commit,files,baseHashes},null,2));
git('archive','--format=tar',`--output=${out}/profile-payment.tar`,commit,'--',...files);
console.log(JSON.stringify({parent,commit,count:files.length}));
