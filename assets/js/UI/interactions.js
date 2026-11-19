/* Enhances existing AJAX views without replacing their actions or data. */
(() => {
 'use strict';
 const listStates=new Map();
 function listState(){const key=String(user?.id||'guest')+'|'+(location.hash||'#dashboard');if(!listStates.has(key))listStates.set(key,{});return listStates.get(key);}
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)'), tables=new WeakMap();
 function busy(button,active){if(!button)return;if(button.form?.id==='editor-form')button.form.dataset.submitting=String(active);button.disabled=active;button.setAttribute('aria-busy',String(active));}
 function enhanceTable(table){
  // Audit logs are already searched and paginated on the server; preserve their ordering.
  if(tables.has(table)||table.closest('#log-results'))return;
  const body=table.tBodies[0], head=table.tHead?.rows[0];if(!body||!head)return;
  const rows=[...body.rows];if(!rows.length)return;
  const wrapper=table.closest('.table-responsive')||table;
  const external=table.closest('#content')?document.querySelector('#table-search'):null;
  const state=external?listState():{};
  let current=state.page||1, query='', sortColumn=-1, ascending=true,perPage=state.pageSize||10;
  const footer=document.createElement('div');footer.className='table-pagination';
  footer.innerHTML='<span class="table-page-summary" role="status" aria-live="polite"></span><div class="table-page-actions"><button type="button" class="btn btn-light btn-sm" aria-label="Previous table page">Previous</button><button type="button" class="btn btn-light btn-sm" aria-label="Next table page">Next</button></div>';
  const noResults=document.createElement('div');noResults.className='table-empty';noResults.hidden=true;noResults.textContent='No matching records. Try another search or filter.';
  wrapper.after(footer);footer.before(noResults);
  const [previous,next]=footer.querySelectorAll('button');
  const size=document.createElement('select');size.className='form-select table-page-size';size.setAttribute('aria-label','Records per page');for(const amount of [10,25,50])size.add(new Option(amount+' per page',String(amount)));size.value=String(perPage);footer.querySelector('.table-page-actions').prepend(size);size.onchange=()=>{perPage=Number(size.value);update(true);};
  const collator=new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
  const value=(row,index)=>{
   const cell=row.cells[index];
   return cell?.dataset.sortValue ?? (cell?.querySelector('strong')?.textContent||cell?.textContent||'').trim();
  };
  function update(reset=false){
   if(reset)current=1;
   const filtered=rows.filter(row=>row.dataset.filtered!=='false'&&(!query||row.textContent.toLowerCase().includes(query)));
   const pages=Math.max(1,Math.ceil(filtered.length/perPage));current=Math.min(current,pages);state.page=current;state.pageSize=perPage;
   const shown=new Set(filtered.slice((current-1)*perPage,current*perPage));
   rows.forEach(row=>{row.hidden=!shown.has(row);});
   previous.disabled=current<=1;next.disabled=current>=pages;
   footer.hidden=rows.length<=10;
   footer.querySelector('.table-page-summary').textContent=filtered.length?`${(current-1)*perPage+1}–${Math.min(current*perPage,filtered.length)} of ${filtered.length} records · Page ${current} of ${pages}`:'0 records';
   // The catalog has its own empty state shared with card view.
   noResults.hidden=filtered.length>0||!!table.closest('#catalog-items');
  }
  tables.set(table,{update});
  previous.onclick=()=>{current--;update();};next.onclick=()=>{current++;update();};
  if(!external&&rows.length>6){
   const toolbar=document.createElement('div');toolbar.className='table-tools';
   const input=document.createElement('input');input.className='form-control';input.type='search';input.placeholder='Search these records';input.setAttribute('aria-label','Search table records');
   toolbar.append(input);wrapper.before(toolbar);
   input.addEventListener('input',()=>{query=input.value.trim().toLowerCase();update(true);});
  }
  [...head.cells].forEach((th,index)=>{
   const label=th.textContent.trim();if(!label||/actions?/i.test(label))return;
   const button=document.createElement('button');button.type='button';button.className='table-sort';button.textContent=label;button.setAttribute('aria-label',`Sort by ${label}`);
   th.replaceChildren(button);th.setAttribute('aria-sort','none');
   button.onclick=()=>{
    ascending=sortColumn===index?!ascending:true;sortColumn=index;
    rows.sort((a,b)=>{
     const av=value(a,index),bv=value(b,index),an=av.replace(/[\u20b1$,]/g,''),bn=bv.replace(/[\u20b1$,]/g,'');
     const numeric=/^-?\d+(\.\d+)?$/.test(an)&&/^-?\d+(\.\d+)?$/.test(bn);
     return (numeric?Number(an)-Number(bn):collator.compare(av,bv))*(ascending?1:-1);
    });
    const fragment=document.createDocumentFragment();rows.forEach(row=>fragment.append(row));body.append(fragment);
    [...head.cells].forEach(cell=>{if(cell.hasAttribute('aria-sort'))cell.setAttribute('aria-sort',cell===th?(ascending?'ascending':'descending'):'none');});update(true);
   };
  });
  update();
 }
 function enhanceFields(root){
  root.querySelectorAll('input[required],textarea[required],select[required]').forEach(field=>{if(field.id)root.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.classList.add('is-required');});
  root.querySelectorAll('textarea[maxlength]').forEach(field=>{
   if(field.dataset.counterReady)return;field.dataset.counterReady='true';
   const counter=document.createElement('small');counter.className='field-counter';
   const update=()=>{counter.textContent=`${field.value.length.toLocaleString()} / ${field.maxLength.toLocaleString()}`;};
   field.after(counter);field.addEventListener('input',update);update();
  });
  root.querySelectorAll('#editor-form input[type="password"]').forEach(field=>{
   if(field.closest('.field-password'))return;
   const wrap=document.createElement('div');wrap.className='field-password';field.before(wrap);wrap.append(field);
   const toggle=document.createElement('button');toggle.type='button';toggle.textContent='Show';toggle.setAttribute('aria-label','Show '+(root.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent||'password'));toggle.setAttribute('aria-pressed','false');
   toggle.onclick=()=>{const show=field.type==='password';field.type=show?'text':'password';toggle.textContent=show?'Hide':'Show';toggle.setAttribute('aria-pressed',String(show));toggle.setAttribute('aria-label',(show?'Hide ':'Show ')+(root.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent||'password'));};wrap.append(toggle);
  });
 }
 let watchedForm=null,baseline='';
 const formState=form=>JSON.stringify([...new FormData(form)].map(([key,value])=>[key,value instanceof File?[value.name,value.size,value.lastModified]:value]));
 function markSaved(form){if(form){form.dataset.saved='true';if(form===watchedForm)baseline=formState(form);}}
 function dirty(){return watchedForm?.isConnected && document.getElementById('editor-modal').classList.contains('show') && watchedForm.dataset.saved!=='true' && formState(watchedForm)!==baseline;}
 function discardThen(callback){
  if(!dirty()){callback();return;}
  if(watchedForm.dataset.submitting==='true'){notice('Please wait for the current request to finish.');return;}
  document.querySelector('.discard-banner')?.remove();
  const banner=document.createElement('div');banner.className='discard-banner alert alert-warning';banner.setAttribute('role','alert');
  banner.innerHTML='<strong>Discard your unsaved changes?</strong><p>Your changes have not been saved.</p><div class="d-flex gap-2"><button type="button" class="btn btn-light" data-keep-editing>Keep editing</button><button type="button" class="btn btn-danger" data-discard-changes>Discard changes</button></div>';
  watchedForm.prepend(banner);banner.querySelector('[data-keep-editing]').onclick=()=>{banner.remove();watchedForm.querySelector('input,textarea,select')?.focus();};
  banner.querySelector('[data-discard-changes]').onclick=()=>{markSaved(watchedForm);banner.remove();callback();};banner.querySelector('button').focus();banner.scrollIntoView({block:'nearest'});
 }
 function forceClose(){markSaved(watchedForm);modal.hide();document.querySelectorAll('#editor-form input[type="password"]').forEach(input=>input.value='');}
 document.getElementById('editor-modal').addEventListener('hide.bs.modal',event=>{
  if(watchedForm?.isConnected && watchedForm.dataset.saved!=='true' && watchedForm.dataset.submitting==='true'){event.preventDefault();notice('Please wait for the current request to finish.');return;}
  if(dirty()){event.preventDefault();discardThen(()=>modal.hide());}
 });
 window.addEventListener('beforeunload',event=>{if(dirty()){event.preventDefault();event.returnValue='';}});
 function enhance(root){
  root.querySelectorAll('table.table').forEach(enhanceTable);enhanceFields(root);
  const form=root.querySelector('#editor-form');if(form&&form!==watchedForm){watchedForm=form;baseline=formState(form);form.addEventListener('input',()=>delete form.dataset.saved);form.addEventListener('change',()=>delete form.dataset.saved);}
  root.querySelectorAll('button[aria-label]').forEach(button=>{if(!button.title)button.title=button.getAttribute('aria-label');});
  root.querySelectorAll('.stat-number').forEach(number=>{
   if(number.dataset.animated)return;number.dataset.animated='true';
   const total=Number(number.textContent);if(!Number.isFinite(total)||reduced.matches||total===0)return;
   const final=number.textContent,start=performance.now();number.setAttribute('aria-label',final);
   const tick=now=>{if(!number.isConnected)return;const progress=Math.min(1,(now-start)/280);number.textContent=progress===1?final:String(Math.round(total*(1-Math.pow(1-progress,3))));if(progress<1)requestAnimationFrame(tick);};requestAnimationFrame(tick);
  });
  window.TechTrackTheme?.apply();
 }
 let queued=false;
 const queue=()=>{if(queued)return;queued=true;queueMicrotask(()=>{queued=false;for(const id of ['content','modal-body']){const root=document.getElementById(id);if(root)enhance(root);}});};
 // Only react to new element nodes; counter text updates do not retrigger enhancement.
 const observer=new MutationObserver(records=>{if(records.some(record=>[...record.addedNodes].some(node=>node.nodeType===1)))queue();});
 for(const id of ['content','modal-body']){const root=document.getElementById(id);if(root)observer.observe(root,{childList:true,subtree:true});}
 document.addEventListener('records:filtered',event=>{document.querySelectorAll('#content table.table').forEach(table=>tables.get(table)?.update(!!event.detail?.reset));});
 const sidebarButton=document.getElementById('sidebar-toggle');
 function updateSidebar(){const collapsed=document.documentElement.classList.contains('sidebar-collapsed');sidebarButton.setAttribute('aria-expanded',String(!collapsed));sidebarButton.setAttribute('aria-label',collapsed?'Expand sidebar':'Collapse sidebar');sidebarButton.title=collapsed?'Expand sidebar':'Collapse sidebar';}
 sidebarButton.addEventListener('click',()=>{document.documentElement.classList.add('sidebar-toggling');setTimeout(()=>document.documentElement.classList.remove('sidebar-toggling'),250);const collapsed=document.documentElement.classList.toggle('sidebar-collapsed');try{localStorage.setItem('techtrack.sidebar',collapsed?'collapsed':'expanded');}catch{}updateSidebar();});updateSidebar();
 window.addEventListener('resize',()=>document.documentElement.classList.remove('sidebar-toggling'));
 document.getElementById('editor-modal').addEventListener('shown.bs.modal',()=>{document.querySelector('#editor-form input:not([type="hidden"]):not([disabled]),#editor-form select,#editor-form textarea')?.focus({preventScroll:true});});
 window.TTUI={busy,enhance,listState,markSaved,forceClose,discardThen,loading:()=>'<div class="loading-skeleton" role="status" aria-label="Loading workspace"><span class="visually-hidden">Loading your workspace...</span><div class="skeleton-heading" aria-hidden="true"></div><div class="skeleton-hero" aria-hidden="true"></div><div class="skeleton-cards" aria-hidden="true"><span></span><span></span><span></span><span></span></div></div>'};
 queue();
})();
