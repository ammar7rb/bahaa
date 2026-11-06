(() => {
 'use strict';
 const root=document.getElementById('egypt-shipping-zone-rates');
 if(!root)return;
 const rows=[...root.querySelectorAll('[data-sg-row]')];
 const normalize=text=>text.normalize('NFKC').replace(/[أإآ]/g,'ا').replace(/ى/g,'ي').trim().toLocaleLowerCase();
 root.querySelector('#sg-search').addEventListener('input',event=>{
  const term=normalize(event.target.value);let count=0;
  rows.forEach(row=>{row.hidden=!normalize(row.dataset.name).includes(term);if(!row.hidden)count++;});
  root.querySelector('#sg-empty').hidden=count>0;
  const result=root.querySelector('#sg-results');result.textContent=`${count} ${result.dataset.label}`;
 });
 root.addEventListener('input',event=>{
  if(!event.target.matches('.sg-normal'))return;
  const value=event.target.valueAsNumber;
  event.target.closest('tr').querySelector('.sg-sigma').textContent=Number.isFinite(value)&&value>=0?(value*3).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):'—';
 });
})();
