'use strict';
const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const providerLabel = p => ({ollama:'Ollama Cloud',openai:'OpenAI',gemini:'Google Gemini',meta:'Meta Model API'}[p] || p);
const money = v => new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(Number(v || 0));
const date = v => v ? new Date(v.includes('T') ? v : v.replace(' ','T')).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}) : 'Not scheduled';
const datetime = v => v ? new Date(v.includes('T') ? v : v.replace(' ','T')).toLocaleString('en-PH',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}) : 'Not scheduled';
const paths = {grid:'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',box:'M21 8l-9 5-9-5 M12 13v9 M3 8l9-5 9 5v10l-9 4-9-4z M7 5l10 5',tool:'M14 6a5 5 0 0 0-6 6L3 17a3 3 0 0 0 4 4l5-5a5 5 0 0 0 6-6l-3 3-4-4z',users:'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-4 M16 3a4 4 0 0 1 0 8',calendar:'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2',chat:'M21 11a9 9 0 0 1-9 9H3l2-5a9 9 0 1 1 16-4z M8 10h8 M8 14h5',camera:'M14 6l2-3H8L6 6H3v14h18V6z M16 13a4 4 0 1 1-8 0 4 4 0 0 1 8 0',chart:'M3 3v18h18 M7 16v-5 M12 16V7 M17 16V4',settings:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2',plus:'M12 5v14 M5 12h14',arrow:'M5 12h14 M13 6l6 6-6 6',check:'M5 12l4 4L19 6',upload:'M12 16V3 M7 8l5-5 5 5 M3 16v5h18v-5',clock:'M12 8v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',shield:'M12 2l9 4v6c0 6-9 10-9 10S3 18 3 12V6z M8 12l3 3 5-6'};
const icon = name => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${paths[name] || paths.grid}"/></svg>`;
const pill = value => `<span class="pill ${esc(String(value).toLowerCase().replaceAll(' ','-'))}">${esc(value)}</span>`;
const empty = (title, text, name='box') => `<div class="empty-state">${icon(name)}<strong>${esc(title)}</strong><p>${esc(text)}</p></div>`;
let user = null, token = $('meta[name="csrf-token"]').content, page = '', poll = null, registering = false, selectedThread = null, renderId = 0;
const modal = new bootstrap.Modal($('#editor-modal'));
const toast = new bootstrap.Toast($('#notice'), {delay:5000});
const cache = {products: [], accounts: [], services: []};
const isStaff = () => ['admin','staff'].includes(user?.role);
function notice(message, error=false){ $('#notice-body').textContent=message;$('#notice').classList.toggle('error',error);toast.show(); }
async function api(action, data=null){
 const options={credentials:'same-origin',headers:{'Accept':'application/json'}};
 let url=`api.php?action=${encodeURIComponent(action)}`;
 if(data !== null){options.method='POST';options.headers['X-CSRF-Token']=token;if(data instanceof FormData)options.body=data;else{options.headers['Content-Type']='application/json';options.body=JSON.stringify(data);}}
 const response=await fetch(url,options);
 let result;try{result=await response.json();}catch{throw new Error('The server returned an unexpected response. Check PHP and MySQL.');}
 if(!response.ok){if(response.status===401 && user){user=null;showAuth();}throw new Error(result.error||'Request failed.');}
 return result;
}
// Keep action and query parameters separate in URLs.
async function read(action, params={}){
 const response=await fetch('api.php?'+new URLSearchParams({action,...params}),{credentials:'same-origin'});
 const result=await response.json();if(!response.ok){if(response.status===401){user=null;showAuth();}throw new Error(result.error||'Request failed.');}return result;
}
function heading(title, description, actions='', eyebrow='YOUR WORKSPACE'){return `<div class="page-heading"><div><div class="eyebrow">${esc(eyebrow)}</div><h1>${esc(title)}</h1><p>${esc(description)}</p></div><div class="page-actions">${actions}</div></div>`;}
const button=(label, action, symbol='plus', extra='')=>`<button class="btn btn-primary" data-action="${action}" ${extra}>${icon(symbol)}${esc(label)}</button>`;
function field(label,name,value='',type='text',extra='') {return `<label class="form-label" for="f-${name}">${esc(label)}</label><input class="form-control" id="f-${name}" name="${name}" type="${type}" value="${esc(value)}" ${extra}>`;}
function area(label,name,value='',extra=''){return `<label class="form-label" for="f-${name}">${esc(label)}</label><textarea class="form-control" id="f-${name}" name="${name}" rows="3" ${extra}>${esc(value)}</textarea>`;}
function select(label,name,options,value='',extra=''){return `<label class="form-label" for="f-${name}">${esc(label)}</label><select class="form-select" id="f-${name}" name="${name}" ${extra}>${options.map(o=>{const [v,l]=Array.isArray(o)?o:[o,o];return `<option value="${esc(v)}" ${String(value)===String(v)?'selected':''}>${esc(l)}</option>`;}).join('')}</select>`;}
function showForm(title,fields,onSubmit,label='Save changes'){
 $('#modal-title').textContent=title;
 $('#modal-body').innerHTML=`<form id="editor-form"><div class="alert alert-danger mt-3" role="alert" id="form-error" hidden></div>${fields}<div class="form-actions"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">${esc(label)}</button></div></form>`;
 $('#editor-form').addEventListener('submit',async e=>{e.preventDefault();const btn=$('[type=submit]',e.currentTarget);const form=e.currentTarget;btn.disabled=true;$('#form-error').hidden=true;try{await onSubmit(Object.fromEntries(new FormData(form)),form);modal.hide();}catch(err){$('#form-error').textContent=err.message;$('#form-error').hidden=false;}finally{btn.disabled=false;}});
 modal.show();
}
function showAuth(){clearInterval(messagePoll);messagePoll=null;closeMenu();clearInterval(poll);$('#workspace').hidden=true;$('#auth').hidden=false;}
function showWorkspace(){
 $('#auth').hidden=true;$('#workspace').hidden=false;$('#sidebar-name').textContent=user.name;$('#avatar').textContent=user.name.split(' ').map(s=>s[0]).slice(0,2).join('');$('#role-badge').textContent=user.role;
 $('#today').textContent=new Date().toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric',timeZone:'Asia/Manila'});
 const nav=[['dashboard','Overview','grid'],...(isStaff()?[['inventory','Inventory','box'],['customers','Customers','users']]:[]),['services',user.role==='customer'?'My services':'Service requests','tool'],...(user.role!=='customer'?[['schedule','Technician schedule','calendar']]:[]),...(user.role!=='technician'?[['messages','Messages','chat'],['planner','CCTV planner','camera']]:[]),...(isStaff()?[['reports','Reports','chart']]:[]),...(user.role==='admin'?[['users','Team & accounts','users'],['user-logs','User logs','clock'],['audit-logs','Audit logs','shield'],['settings','Settings','settings']]:[])];
 $('#navigation').innerHTML=nav.map(([id,label,i])=>`<a href="#${id}" class="nav-item" data-page="${id}">${icon(i)}<span>${esc(label)}</span>${id==='messages'?'<span class="unread-badge" data-unread hidden></span>':''}${id==='planner'?'<span class="nav-new">AI</span>':''}</a>`).join('');
 startMessageNotifications();
 route();
}
let authMode = 'login', resetToken = '';
function hidePasswords(){
 $$('[data-password-toggle]').forEach(button=>{
  const input=document.getElementById(button.dataset.passwordToggle);
  input.type='password';button.textContent='Show';button.setAttribute('aria-pressed','false');
  button.setAttribute('aria-label',input.id==='confirm-password'?'Show confirm password':'Show password');
 });
}
$$('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{
 const input=document.getElementById(button.dataset.passwordToggle), visible=input.type==='password';
 input.type=visible?'text':'password';button.textContent=visible?'Hide':'Show';button.setAttribute('aria-pressed',String(visible));
 button.setAttribute('aria-label',`${visible?'Hide':'Show'} ${input.id==='confirm-password'?'confirm password':'password'}`);
}));
function setAuthMode(mode){
 authMode=mode;registering=mode==='register';
 const forgot=mode==='forgot', reset=mode==='reset';
 $('#register-fields').hidden=!registering;$('#register-name').required=registering;$('#register-name').disabled=!registering;
 $('#email-fields').hidden=reset;$('#login-email').disabled=reset;$('#login-email').required=!reset;
 $('#password-fields').hidden=forgot;$('#login-password').disabled=forgot;$('#login-password').required=!forgot;
 $('#login-password').minLength=registering||reset?10:1;$('#login-password').autocomplete=registering||reset?'new-password':'current-password';
 $('#login-password').value='';$('#confirm-password').value='';hidePasswords();
 $('#password-label').textContent=reset?'New password':'Password';
 $('#login-password').placeholder=registering||reset?'At least 10 characters':'Enter your password';
 $('#confirm-password-fields').hidden=!reset;$('#confirm-password').disabled=!reset;$('#confirm-password').required=reset;
 $('#forgot-password-wrap').hidden=mode!=='login';
 $('#auth-title').textContent={login:'Good to have you back.',register:'Create your account.',forgot:'Forgot your password?',reset:'Choose a new password.'}[mode];
 $('#auth-subtitle').textContent={login:'Sign in to manage your services and stay connected.',register:'Create your customer account to request services and plan your CCTV.',forgot:'Enter your account email and we will send you a link to reset your password.',reset:'Use at least 10 characters for your new password.'}[mode];
 $('#auth-submit').textContent={login:'Sign in',register:'Create account',forgot:'Send reset link',reset:'Reset password'}[mode];
 $('#auth-toggle').textContent=mode==='login'?'Create an account':mode==='register'?'Sign in':'Back to sign in';
 $('#auth-switch-label').textContent=mode==='login'?'New to NCPCS?':mode==='register'?'Already have an account?':'';
 $('#auth-error').hidden=true;$('#auth-success').hidden=true;
}
$('#auth-toggle').addEventListener('click',()=>{resetToken='';setAuthMode(authMode==='login'?'register':'login');});
function openPasswordResetFromUrl(){
 const recovery=new URLSearchParams(location.hash.slice(1)).get('reset');
 if(recovery===null)return false;
 resetToken=recovery;history.replaceState(null,'',location.pathname);
 showAuth();setAuthMode('reset');return true;
}
$('#forgot-password').addEventListener('click',()=>{setAuthMode('forgot');$('#login-email').focus();});
$('#auth-form').addEventListener('submit',async e=>{
 e.preventDefault();const form=e.currentTarget, mode=authMode;
 const data=Object.fromEntries(new FormData(form));
 $('#auth-submit').disabled=true;$('#auth-toggle').disabled=true;$('#forgot-password').disabled=true;$('#auth-error').hidden=true;$('#auth-success').hidden=true;
 try{
  if(mode==='reset' && data.password!==data.confirm_password)throw new Error('The passwords do not match.');
  const action={login:'login',register:'register',forgot:'forgot_password',reset:'reset_password'}[mode];
  if(mode==='reset')data.reset_token=resetToken;
  const r=await api(action,data);
  if(r.csrf)token=r.csrf;
  if(mode==='forgot'||mode==='reset'){
   if(mode==='reset'){resetToken='';user=null;form.reset();setAuthMode('login');}
   $('#auth-success').textContent=r.message;$('#auth-success').hidden=false;
  }else{user=r.user;form.reset();hidePasswords();showWorkspace();}
 }catch(err){$('#auth-error').textContent=err.message;$('#auth-error').hidden=false;}
 finally{$('#auth-submit').disabled=false;$('#auth-toggle').disabled=false;$('#forgot-password').disabled=false;}
});
$('#logout').addEventListener('click',async()=>{try{await api('logout',{});location.reload();}catch(e){notice(e.message,true);}});
$('#profile-button').addEventListener('click',()=>{showForm('Your profile',field('Full name','name',user.name,'text','required maxlength="120"')+field('Phone','phone',user.phone,'tel','maxlength="40"')+area('Address','address',user.address,'maxlength="500"')+'<div class="section-label">Change password (optional)</div>'+field('Current password','current_password','','password','autocomplete="current-password"')+field('New password · minimum 10 characters','new_password','','password','minlength="10" maxlength="200" autocomplete="new-password"'),async data=>{await api('profile',data);user=(await api('session')).user;showWorkspace();notice('Profile updated.');});});
$('#menu-button').addEventListener('click',()=>{$('#sidebar').classList.add('open');$('#sidebar-backdrop').hidden=false;$('#menu-button').setAttribute('aria-expanded','true');});
$('#sidebar-backdrop').addEventListener('click',closeMenu);
function closeMenu(){$('#sidebar').classList.remove('open');$('#sidebar-backdrop').hidden=true;$('#menu-button').setAttribute('aria-expanded','false');}
window.addEventListener('hashchange',()=>{if(!openPasswordResetFromUrl() && user)route();});
async function route(){
 clearInterval(poll);poll=null;closeMenu();page=(location.hash.slice(1)||'dashboard').split('/')[0];
 if(!$(`[data-page="${CSS.escape(page)}"]`)){page='dashboard';}
 $$('.nav-item').forEach(n=>n.classList.toggle('active',n.dataset.page===page));$('#breadcrumb').textContent=$(`[data-page="${page}"] span`)?.textContent||'Overview';
 const rid=++renderId;$('#content').innerHTML='<div class="loading-state">Loading your workspace…</div>';
 try{await ({dashboard:dashboard,inventory:inventory,customers:r=>accounts(true,r),users:r=>accounts(false,r),services:services,schedule:r=>services(true,r),messages:messages,planner:planner,reports:reports,settings:settingsPage,'user-logs':r=>logsPage('user_logs',r),'audit-logs':r=>logsPage('audit',r)}[page]||dashboard)(rid);}catch(e){if(rid===renderId)$('#content').innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div><button class="btn btn-light" data-action="refresh">Try again</button>`;}
}
function render(html,rid){if(rid!==renderId)return false;$('#content').innerHTML=html;return true;}
function stat(title,value,foot,i){return `<div class="stat-card"><div class="stat-top">${esc(title)}<span class="stat-icon">${icon(i)}</span></div><div class="stat-number">${esc(value)}</div><div class="stat-foot">${esc(foot)}</div></div>`;}
function serviceRows(items){return items.map(s=>`<tr><td><strong>${esc(s.title)}</strong><small>SR-${String(s.id).padStart(4,'0')} · ${esc(s.type)}</small></td>${user.role!=='customer'?`<td>${esc(s.customer_name)}</td>`:''}<td>${pill(s.status)}</td><td>${esc(datetime(s.scheduled_at))}<small>${esc(s.technician_name||'Awaiting assignment')}</small></td><td><button class="btn btn-light btn-sm" data-action="service-detail" data-id="${s.id}">View</button></td></tr>`).join('');}
const serviceTable=items=>`<div class="table-responsive"><table class="table"><thead><tr><th>Service request</th>${user.role!=='customer'?'<th>Customer</th>':''}<th>Status</th><th>Appointment</th><th></th></tr></thead><tbody>${serviceRows(items)}</tbody></table></div>`;

let messagePoll=null, messageBusy=false, lastUnread=-1;
async function refreshMessageBadge(){
 if(!user || user.role==='technician' || messageBusy || document.hidden)return;
 const accountId=user.id;messageBusy=true;
 try{
  const d=await api('message_unread');if(!user || user.id!==accountId)return;
  const count=Number(d.unread)||0;
  $$('[data-unread]').forEach(b=>{b.hidden=count===0;b.textContent=count>99?'99+':String(count);});
  $('#message-alert').setAttribute('aria-label',count?`${count} unread messages`:'Messages');
  if(count!==lastUnread){$('#message-announcement').textContent=count?`${count} unread messages`:lastUnread>0?'All messages read':'';lastUnread=count;}
 }catch(e){if(user){$('#message-alert').title='Unable to refresh unread count. Open Messages to retry.';}}
 finally{messageBusy=false;}
}
function startMessageNotifications(){
 clearInterval(messagePoll);lastUnread=-1;
 $('#message-alert').hidden=user.role==='technician';
 if(user.role==='technician')return;
 refreshMessageBadge();messagePoll=setInterval(refreshMessageBadge,5000);
}
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshMessageBadge();});
document.addEventListener('keydown',e=>{if(e.key==='Escape' && $('#sidebar').classList.contains('open')){closeMenu();$('#menu-button').focus();}});
