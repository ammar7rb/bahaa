document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-va-open]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.vaOpen)?.showModal()));
  document.querySelectorAll('[data-va-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
  const select=document.getElementById('va-package-select'),price=document.getElementById('va-package-price');
  if(select&&price){const sync=()=>{price.value=select.selectedOptions[0]?.dataset.price||''};select.addEventListener('change',sync);sync()}
  const search=document.getElementById('va-search'),results=document.getElementById('va-suggestions');let timer,sequence=0;
  if(search&&results)search.addEventListener('input',()=>{clearTimeout(timer);const term=search.value.trim(),run=++sequence;results.replaceChildren();if(term.length<2)return;
    timer=setTimeout(async()=>{try{const response=await fetch(search.dataset.vaLookup+'?q='+encodeURIComponent(term),{headers:{Accept:'application/json'}});if(!response.ok)throw Error('lookup');const rows=await response.json();if(run!==sequence)return;
      for(const row of rows){const link=document.createElement('a');const url=new URL(window.location.href);url.searchParams.delete('q');url.searchParams.set('seller_id',row.id);link.href=url.toString();link.textContent=`${row.reference} · ${row.name} · ${row.phone||'بدون هاتف'} · ${row.status}`;results.append(link)}
      if(!rows.length)results.textContent='لا توجد حسابات مطابقة.';
    }catch{if(run===sequence)results.textContent='تعذر جلب الاقتراحات. استخدم زر البحث.'}},220);
  });
});
