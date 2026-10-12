import fs from 'node:fs';
import cp from 'node:child_process';
const root='E:/xampp/htdocs/ba',out='E:/project/deliverables/customer-packages';
const env={...process.env,GIT_INDEX_FILE:`${out}/live-credit-github.index`};
const git=(...args)=>cp.execFileSync('git',args,{cwd:root,env,encoding:'utf8'}).trim();
const manifest=JSON.parse(fs.readFileSync(`${out}/live-credit-manifest.json`));
const branch='codex/customer-updates-profile-payments',parent=git('rev-parse',branch);
git('read-tree',parent);
for(const file of manifest.files){const data=cp.execFileSync('git',['show',`${manifest.commit}:${file}`],{cwd:root});const hash=cp.execFileSync('git',['hash-object','-w','--stdin'],{cwd:root,env,input:data,encoding:'utf8'}).trim();git('update-index','--add','--cacheinfo',`100644,${hash},${file}`);}
git('add','-f','--','tests/Unit/CustomerMonthlyPackagesTest.php','tests/Unit/CustomerPackageBladeSyntaxTest.php','tools/package-live-credit-indicator.mjs','tools/deploy-live-credit-indicator.php','tools/verify-live-credit-indicator.php','tools/test-credit-indicator.mjs','tools/publish-live-credit-indicator.mjs');
const commit=git('commit-tree',git('write-tree'),'-p',parent,'-m','Remove customer coupon entry and add live cart credit strip with checkout blocking');
git('update-ref',`refs/heads/${branch}`,commit,parent);
git('update-ref','refs/heads/codex/customer-vps-release',manifest.commit,manifest.parent);
console.log(JSON.stringify({parent,commit,branch}));
