const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('public/assets/back-end/js/admin-v2.js','utf8');
const position=source.slice(source.indexOf('  function positionDropdownMenu(btn)'),source.indexOf('  function bindDropdowns()'));
function place(width,height,direction,rect,headerBottom=60){
 const style={};
 const menu={classList:{contains:x=>x==='admin-inbox'},style,getBoundingClientRect:()=>({width:Math.min(440,width-24)})};
 const button={getBoundingClientRect:()=>rect,closest:selector=>selector==='.v2-header'?{getBoundingClientRect:()=>({bottom:headerBottom})}:{querySelector:()=>menu}};
 const context={window:{innerWidth:width,innerHeight:height},document:{documentElement:{}},getComputedStyle:()=>({direction}),button};
 vm.runInNewContext(position+';positionDropdownMenu(button);',context);
 return style;
}
test('RTL and LTR notification panels stay inside viewport edges',()=>{
 for(const direction of ['rtl','ltr']) for(const left of [5,1200]) {
  const result=place(1280,720,direction,{left,right:left+44,bottom:50});
  assert.ok(parseFloat(result.left)>=12);
  assert.ok(parseFloat(result.left)+440<=1268);
  assert.equal(result.top,'58px');
 }
});
test('mobile panel clears the complete header and fits remaining height',()=>{
 const result=place(390,844,'rtl',{left:14,right:58,bottom:140},164);
 assert.equal(result.left,'12px');
 assert.equal(result.top,'172px');
 assert.equal(result.maxHeight,'480px');
});

test('failed photos recover once and then retain a category icon',()=>{
 const code=fs.readFileSync('public/assets/back-end/js/admin-workspace.js','utf8');
 const helper=code.slice(code.indexOf('    function imageFallback(img)'),code.indexOf("    document.addEventListener('error'"));
 const icons=[];
 const media={querySelector:()=>icons[0],append:icon=>icons.push(icon)};
 const img={dataset:{},closest:()=>media};
 const context={config:{imageFallback:'/fallback.png'},document:{createElement:()=>({setAttribute(){}})},img};
 vm.runInNewContext(helper+';imageFallback(img);',context);
 assert.equal(img.src,'/fallback.png');
 assert.equal(img.hidden,undefined);
 vm.runInNewContext(helper+';imageFallback(img);imageFallback(img);',context);
 assert.equal(img.hidden,true);
 assert.equal(icons.length,1);
 assert.match(code,/img.complete && img.naturalWidth===0/);
 assert.doesNotMatch(code,/set(?:Timeout|Interval)\(refresh/);
});

 test('relative labels advance from the real offset timestamp across units',()=>{
 const code=fs.readFileSync('public/assets/back-end/js/admin-workspace.js','utf8');
 const helper=code.slice(code.indexOf('    function relativeTime('),code.indexOf('    function updateTimes('));
 const context={Intl,Date,Number,Math};vm.runInNewContext(helper,context);
 const stamp='2026-10-10T10:00:00+03:00', start=Date.parse(stamp);
 assert.equal(context.relativeTime(stamp,start+3000,'en'),'3 seconds ago');
 assert.equal(context.relativeTime(stamp,start+65000,'en'),'1 minute ago');
 assert.equal(context.relativeTime(stamp,start+7200000,'en'),'2 hours ago');
 assert.equal(context.relativeTime('2026-10-10T07:00:00Z',start+3000,'en'),'3 seconds ago');
 assert.match(context.relativeTime(stamp,start+65000,'ar'),/دقيقة/);
 assert.equal(context.relativeTime('invalid',start,'ar'),null);
 });
