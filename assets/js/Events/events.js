'use strict';
document.addEventListener('click',async e=>{
 const b=e.target.closest('[data-action]');if(!b||b.disabled)return;const id=Number(b.dataset.id);TTUI.busy(b,true);try{switch(b.dataset.action){
 case 'refresh':await route();break;
 case 'new-product':editProduct();break;case 'edit-product':editProduct(id);break;case 'stock':stockForm(id);break;
 case 'archive-product':showForm('Archive product',`<div class="confirm-warning"><span aria-hidden="true">${icon('box')}</span><div><h3>Archive ${esc(cache.products.find(p=>Number(p.id)===id)?.name||'this product')}?</h3><p>It will leave the active catalog. Its stock must be zero; movement history will remain available.</p></div></div>`,async()=>{await api('product_archive',{id});await route();notice('Product archived.');},'Archive');break;
 case 'restore-product':showForm('Restore product',`<p class="mt-3">Return <strong>${esc(cache.products.find(p=>Number(p.id)===id)?.name||'this product')}</strong> to the active catalog? Its existing stock history and price will be retained.</p>`,async()=>{await api('product_restore',{id});await route();notice('Product restored.');},'Restore');break;
 case 'new-customer':editUser(null,true);break;case 'new-user':editUser();break;case 'edit-user':editUser(id);break;
 case 'new-service':await newService();break;case 'service-detail':await serviceDetail(id);break;
 case 'upload-blueprint':uploadBlueprint();break;
 case 'delete-blueprint':deleteBlueprint(id,b.dataset.title);break;
 case 'print':window.print();break;
 case 'customer-history':{const d=await api('services');$('#modal-title').textContent='Customer service history';$('#modal-body').innerHTML=d.items.filter(s=>Number(s.customer_id)===id).length?serviceTable(d.items.filter(s=>Number(s.customer_id)===id)):empty('No services recorded','This customer has not requested a service yet.','tool');modal.show();break;}
 case 'movements':{const d=await api('movements');$('#modal-title').textContent='Stock movement history · latest 200';$('#modal-body').innerHTML=`<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Change</th><th>Reason</th><th>Recorded by</th></tr></thead><tbody>${d.items.map(m=>`<tr><td>${esc(m.product_name)}<small>${datetime(m.created_at)}</small></td><td>${Number(m.quantity)>0?'+':''}${m.quantity}</td><td>${esc(m.reason)}${m.service_id?`<small>SR-${m.service_id}</small>`:''}</td><td>${esc(m.user_name)}</td></tr>`).join('')}</tbody></table></div>`;modal.show();break;}
 case 'audit':location.hash='audit-logs';break;
 }}catch(err){notice(err.message,true);}finally{TTUI.busy(b,false);}
});
(async()=>{
 try{if(sessionStorage.getItem('techtrack.signedOut')){sessionStorage.removeItem('techtrack.signedOut');notice('You have been signed out.');}}catch{}
 const recovery=openPasswordResetFromUrl();
 const openRegistration=new URLSearchParams(location.search).get('register')==='1';
 if(openRegistration)history.replaceState(null,'',location.pathname+location.hash);
 try{
  const r=await api('session');user=r.user;token=r.csrf;
  if(recovery){showAuth();setAuthMode('reset');}
  else if(user)showWorkspace();else{showAuth();if(openRegistration)$('#auth-toggle').click();}
  const googleError=$('#google-auth-feedback').dataset.error;
  if(!user && !recovery && r.expired){$('#auth-error').textContent='Your session expired or was revoked. Sign in again to continue.';$('#auth-error').hidden=false;}
  if(!user && !recovery && googleError){$('#auth-error').textContent=googleError;$('#auth-error').hidden=false;}
 }catch(e){showAuth();$('#auth-error').textContent=e.message;$('#auth-error').hidden=false;}
})();
