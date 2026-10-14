import fs from 'node:fs';
import cp from 'node:child_process';
const root='E:/project/user-app-main';
const snapshot='E:/project/deliverables/customer-stage7910-20261014/mobile-check';
const files=cp.execFileSync('git',['ls-tree','-r','--name-only','codex/customer-stages7910','--','lib','assets/language','pubspec.yaml','pubspec.lock'],{cwd:root,encoding:'utf8'}).trim().split('\n');
const norm=b=>b.toString('utf8').replace(/^\uFEFF/,'').replace(/\r\n/g,'\n');
const mismatches=[];
for(const file of files){
 if(!fs.existsSync(`${root}/${file}`)||!fs.existsSync(`${snapshot}/${file}`)||norm(fs.readFileSync(`${root}/${file}`))!==norm(fs.readFileSync(`${snapshot}/${file}`)))mismatches.push(file);
}
const head=cp.execFileSync('git',['ls-remote','origin','refs/heads/main'],{cwd:root,encoding:'utf8'}).split(/\s/)[0];
const expected=cp.execFileSync('git',['rev-parse','codex/customer-stages7910'],{cwd:root,encoding:'utf8'}).trim();
const result={sourceFiles:files.length,mismatches,remoteMain:head,expected,matched:head===expected&&mismatches.length===0};
fs.writeFileSync('E:/project/deliverables/customer-stage7910-20261014/mobile-source-verification.json',JSON.stringify(result,null,2));
console.log(JSON.stringify(result));if(!result.matched)process.exit(1);
