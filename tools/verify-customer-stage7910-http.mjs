import fs from 'node:fs';
const root='https://sigma-eg.net';
const checks=[];
for(const path of ['/','/business-page/veterinary-services','/business-page/medical-devices-accessories','/api/v1/config','/customer/account-deletion','/customer/password-recovery']){
 const response=await fetch(root+path,{redirect:'manual'});
 const status=response.status;
 const expected=path==='/customer/account-deletion'?302:200;
 checks.push({path,status,pass:status===expected});
}
const response=await fetch(root+'/api/v1/customer/account-deletion/confirm',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({confirmation:true})});
checks.push({path:'/api/v1/customer/account-deletion/confirm',status:response.status,pass:response.status===401});
for(const language of ['ar','en']){
 const response=await fetch(root+'/api/v1/business-pages?type=pages',{headers:{lang:language}});
 const data=await response.json();
 const vet=data.filter(page=>page.slug==='veterinary-services');
 checks.push({language,path:'business-pages',status:response.status,pass:response.ok&&vet.length===1&&vet[0].title===(language==='ar'?'خدمات بيطرية':'Veterinary Services')});
}
fs.writeFileSync('E:/project/deliverables/customer-stage7910-20261014/http-verification.json',JSON.stringify(checks,null,2));
console.log(JSON.stringify(checks,null,2));
if(checks.some(check=>!check.pass))process.exit(1);
