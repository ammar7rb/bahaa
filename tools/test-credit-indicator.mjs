import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const script=fs.readFileSync(new URL('../public/assets/front-end/js/customer-purchasing-limit.js',import.meta.url),'utf8');
function fixture(reserved=0) {
  const listeners={},parts={};
  for(const selector of ['[data-limit-cart]','[data-limit-projected]','[data-limit-status]','[data-limit-progress]','[role="progressbar"]']) parts[selector]={style:{},setAttribute(name,value){this[name]=value;}};
  const strip={dataset:{available:'3000',granted:'3000',cart:'1000',reserved:String(reserved),items:JSON.stringify([{id:1,price:1000,quantity:1}])},querySelector:selector=>parts[selector]};
  const input={value:'1',offsetParent:{},matches:()=>true};
  const button={tagName:'BUTTON',disabled:false,dataset:{},attrs:{},classList:{contains:()=>false},hasAttribute(name){return name in this.attrs;},setAttribute(name,value){this.attrs[name]=value;},removeAttribute(name){delete this.attrs[name];}};
  const document={addEventListener(name,callback){listeners[name]=callback;},querySelectorAll(selector){if(selector==='[data-purchasing-limit]')return [strip];if(selector.startsWith('input['))return [input];return [button];},querySelector(selector){return selector.includes('data-insufficient') ? (strip.dataset.insufficient==='true'?strip:null):strip;}};
  vm.runInNewContext(script,{document,window:{},setInterval(){},setTimeout(){},clearTimeout(){}});
  listeners.DOMContentLoaded();
  return {strip,button,parts,quantity(value){input.value=String(value);listeners.input({target:input});}};
}
const f=fixture();
assert.equal(f.strip.dataset.tone,'green');assert.equal(f.button.disabled,false);
f.quantity(1.5);assert.equal(f.strip.dataset.tone,'orange');
f.quantity(2.7);assert.equal(f.strip.dataset.tone,'red');
f.quantity(3);assert.equal(f.button.disabled,false);
f.quantity(4);assert.equal(f.button.disabled,true);assert.equal(f.strip.dataset.insufficient,'true');
f.quantity(2);assert.equal(f.button.disabled,false);assert.equal(f.strip.dataset.insufficient,'false');
const reserved=fixture(1000);
assert.equal(reserved.parts['[role="progressbar"]']['aria-valuenow'],'100');
reserved.quantity(4);assert.equal(reserved.button.disabled,true);
console.log('PASS: live quantities, color boundaries, exact limit, over-limit disable/re-enable, reserved cart counted once');
