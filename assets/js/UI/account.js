/* Account controls use the same CSRF-protected API as other profile actions. */
'use strict';
async function openDevices(){
 const d=await api('auth_devices');
 $('#modal-title').textContent='Signed-in devices';
 $('#modal-body').innerHTML=`<p class="muted mt-3">Review where your account is signed in. Removing a device ends its sessions and remembered sign-in.</p><div class="device-list">${d.items.map(device=>`<article class="device-card"><div><strong>${esc(device.device_label)} ${device.current?'<span class="pill completed">This device</span>':''}</strong><p>${device.remembered?'Remembered browser':'Browser session'}<br>Last active ${esc(datetime(device.last_seen_at))}<br>Expires ${esc(datetime(device.expires_at))}</p></div><button class="btn btn-light btn-sm" type="button" data-revoke-device="${Number(device.id)}">${device.current?'Sign out':'Remove'}</button></article>`).join('')}</div><div class="form-actions"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="revoke-other-devices" ${d.items.filter(x=>!x.current).length?'':'disabled'}>Sign out other devices</button></div>`;modal.show();
 $('#revoke-other-devices').onclick=async e=>{const button=e.currentTarget;if(button.disabled)return;TTUI.busy(button,true);try{await api('revoke_other_devices',{});notice('Other devices have been signed out.');await openDevices();}catch(error){notice(error.message,true);}finally{TTUI.busy(button,false);}};
 $$('[data-revoke-device]').forEach(button=>button.onclick=async()=>{if(button.disabled)return;TTUI.busy(button,true);try{const result=await api('revoke_device',{id:Number(button.dataset.revokeDevice)});if(result.signed_out){TTUI.forceClose();user=null;showAuth();setAuthMode('login');notice('This device has been signed out.');}else{notice('Device signed out.');await openDevices();}}catch(error){notice(error.message,true);}finally{TTUI.busy(button,false);}});
}
document.addEventListener('click',event=>{if(event.target.closest('#manage-devices'))TTUI.discardThen(()=>openDevices().catch(error=>notice(error.message,true)));});
// Caps Lock is a keyboard state, not inferred from password contents.
function capsWarning(event){
 const input=event.target;if(!input.matches('input[type="password"],input[autocomplete="current-password"],input[autocomplete="new-password"]'))return;
 const host=input.closest('.password-control,.field-password')||input;
 let warning=host.nextElementSibling;
 if(!warning?.classList.contains('caps-warning')){warning=document.createElement('small');warning.className='caps-warning';warning.setAttribute('role','status');warning.textContent='Caps Lock is on.';warning.hidden=true;host.after(warning);}
 warning.hidden=event.type==='focusout'||!event.getModifierState?.('CapsLock');
}
for(const event of ['keyup','keydown','focusout'])document.addEventListener(event,capsWarning);
