/* Run in the head so the saved theme is applied before the first paint. */
(() => {
 'use strict';
 const key='techtrack.theme', root=document.documentElement, media=window.matchMedia('(prefers-color-scheme: dark)');
 let preference='system';
 try { const saved=localStorage.getItem(key);if(['light','dark'].includes(saved))preference=saved; } catch {}
 function apply(){
  const dark=preference==='dark'||(preference==='system'&&media.matches);
  root.dataset.theme=dark?'dark':'light';root.dataset.bsTheme=root.dataset.theme;root.style.colorScheme=root.dataset.theme;
  document.querySelectorAll('[data-theme-toggle]').forEach(button=>{
   button.setAttribute('aria-pressed',String(dark));button.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');button.title=dark?'Switch to light mode':'Switch to dark mode';
   const text=button.querySelector('[data-theme-label]');if(text)text.textContent=dark?'Light mode':'Dark mode';
  });
  document.querySelectorAll('[data-theme-choice]').forEach(select=>{select.value=preference;});
 }
 function set(value){preference=['light','dark'].includes(value)?value:'system';try{if(preference==='system')localStorage.removeItem(key);else localStorage.setItem(key,preference);}catch{}apply();}
 apply();
 document.addEventListener('DOMContentLoaded',apply);
 document.addEventListener('click',event=>{if(event.target.closest('[data-theme-toggle]'))set(root.dataset.theme==='dark'?'light':'dark');});
 document.addEventListener('change',event=>{if(event.target.matches('[data-theme-choice]'))set(event.target.value);});
 media.addEventListener('change',()=>{if(preference==='system')apply();});
 window.addEventListener('storage',event=>{if(event.key===key||event.key===null){preference=['light','dark'].includes(event.newValue)?event.newValue:'system';apply();}});
 window.TechTrackTheme={apply,get preference(){return preference;}};
 try{if(localStorage.getItem('techtrack.sidebar')==='collapsed')root.classList.add('sidebar-collapsed');}catch{}
})();
