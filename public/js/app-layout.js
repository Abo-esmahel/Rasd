(function(){
    const html=document.documentElement;
    const t=document.getElementById('theme-toggle');
    function sync(){
        const d=html.classList.contains('dark');
        document.querySelectorAll('.sun-icon').forEach(e=>e.classList.toggle('hidden',d));
        document.querySelectorAll('.moon-icon').forEach(e=>e.classList.toggle('hidden',!d));
    }
    function applyStoredTheme(){
        try{
            var s=localStorage.getItem('theme')||localStorage.getItem('rasd_theme');
            if(s==='light') html.classList.remove('dark'); else html.classList.add('dark');
            sync();
        }catch(e){}
    }
    function toggle(){
        html.classList.toggle('dark');
        const d=html.classList.contains('dark');
        try{localStorage.setItem('theme',d?'dark':'light');localStorage.setItem('rasd_theme',d?'dark':'light');}catch(e){}
        sync();
    }
    sync();
    t?.addEventListener('click',toggle);

    window.addEventListener('pageshow', applyStoredTheme);
    window.addEventListener('storage', function(e){ if(e.key==='theme'||e.key==='rasd_theme') applyStoredTheme(); });


    (function(){
        const settingsBtn = document.getElementById('notification-settings-toggle');
        const settingsPanel = document.getElementById('notification-settings-panel');
        if(settingsBtn && settingsPanel){
            settingsBtn.addEventListener('click', (e)=>{
                e.stopPropagation();
                settingsPanel.classList.toggle('hidden');
            });
        }

    })();

})();
(function(){
    var mBtn=document.getElementById('mobile-menu-btn'),mMenu=document.getElementById('mobile-menu'),mOverlay=document.getElementById('mobile-menu-overlay'),mClose=document.getElementById('mobile-menu-close');
    if(!mBtn||!mMenu) return;
    var mOpen=false,hideT=null,prevOverflow='';
    function setBtn(open){mBtn.setAttribute('aria-expanded',open?'true':'false');var o=mBtn.querySelector('.menu-open'),c=mBtn.querySelector('.menu-close');if(o)o.classList.toggle('hidden',open);if(c)c.classList.toggle('hidden',!open);}
    function openMenu(){
        if(mOpen) return; mOpen=true; clearTimeout(hideT);
        prevOverflow=document.body.style.overflow;
        if(mOverlay)mOverlay.classList.remove('hidden');
        mMenu.classList.remove('hidden'); mMenu.setAttribute('aria-hidden','false');
        requestAnimationFrame(function(){requestAnimationFrame(function(){
            if(!mOpen) return;
            if(mOverlay)mOverlay.classList.add('is-visible');
            mMenu.classList.add('is-open');
        });});
        document.body.style.overflow='hidden'; setBtn(true);
        try{mClose&&mClose.focus({preventScroll:true});}catch(e){try{mClose&&mClose.focus();}catch(_){}}
    }
    function closeMenu(returnFocus){
        if(!mOpen&&mMenu.classList.contains('hidden')) return; mOpen=false; clearTimeout(hideT);
        if(mOverlay)mOverlay.classList.remove('is-visible');
        mMenu.classList.remove('is-open'); mMenu.setAttribute('aria-hidden','true'); setBtn(false);
        var steal=returnFocus!==false&&mMenu.contains(document.activeElement);
        hideT=setTimeout(function(){
            if(mOpen) return;
            if(mOverlay)mOverlay.classList.add('hidden');
            mMenu.classList.add('hidden');
            if(!document.querySelector('[data-modal]:not(.hidden)')&&!document.querySelector('#notification-drawer.is-open')){document.body.style.overflow=prevOverflow||'';}
            if(steal){try{mBtn.focus({preventScroll:true});}catch(e){}}
        },340);
    }
    mBtn.addEventListener('click',function(){mOpen?closeMenu(false):openMenu();});
    if(mClose)mClose.addEventListener('click',function(){closeMenu();});
    if(mOverlay)mOverlay.addEventListener('click',function(){closeMenu(false);});
    document.addEventListener('click',function(e){if(!mOpen)return;if(!mBtn.contains(e.target)&&!mMenu.contains(e.target)){closeMenu(false);}});
    var bell=document.getElementById('notification-bell');
    if(bell)bell.addEventListener('click',function(){closeMenu(false);});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&mOpen){closeMenu();}});
    window.addEventListener('resize',function(){if(window.innerWidth>=768)closeMenu(false);});
    window.__closeMobileMenu=closeMenu;
})();
setTimeout(()=>{document.querySelectorAll('[role="alert"]').forEach(el=>{if(el.textContent.includes(window.RASD_I18N.doneKeyword) || el.textContent.includes(window.RASD_I18N.successKeyword)){el.style.transition='opacity .4s,transform .4s';el.style.opacity='0';el.style.transform='translateY(-6px)';setTimeout(()=>el.remove(),400);}});},5000);
document.querySelectorAll('form:not([data-ajax])').forEach(form=>{form.addEventListener('submit',function(e){const b=this.querySelector('button[type="submit"]:not([formnovalidate])');if(!b||b.dataset.noLoader)return;if(b.disabled&&b.dataset.loaderOn)return;setTimeout(()=>{if(e.defaultPrevented)return;if(!b.dataset.origHtml)b.dataset.origHtml=b.innerHTML;b.dataset.loaderOn='1';b.disabled=true;const __t=window.RASD_I18N.processing;b.innerHTML='<span class="inline-flex items-center gap-2"><svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> '+__t+'</span>';b.classList.add('opacity-80','cursor-wait');},0);});});
window.addEventListener('pageshow',()=>{document.querySelectorAll('button[data-loader-on]').forEach(b=>{if(b.dataset.origHtml!=null)b.innerHTML=b.dataset.origHtml;b.disabled=false;b.classList.remove('opacity-80','cursor-wait');delete b.dataset.loaderOn;delete b.dataset.origHtml;});});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('[data-modal]').forEach(m=>m.classList.add('hidden'));document.body.style.overflow='';}});
function openModal(id){const el=document.getElementById(id);if(!el)return;el.classList.remove('hidden');el.style.display='';el.style.visibility='';document.body.style.overflow='hidden';}
function closeModal(id){const el=document.getElementById(id);if(!el)return;el.classList.add('hidden');el.style.display='';document.body.style.overflow='';}
window.openModal=openModal;window.closeModal=closeModal;

window.toast=function(msg){
    try{
        var box=document.getElementById('rasd-toast');var inner=document.getElementById('rasd-toast-inner');
        if(!box||!inner){return;}
        inner.textContent=String(msg||'').slice(0,120);
        box.classList.remove('hidden');
        clearTimeout(window.__toastT);
        window.__toastT=setTimeout(function(){box.classList.add('hidden');},2600);
    }catch(e){}
};
window.notifyError=function(msg){window.toast(msg||window.RASD_I18N.errorOccurred);};

window.copyTextToClipboard=function(text){
    return new Promise(function(resolve){
        text=String(text==null?'':text);
        function legacy(){
            try{
                var ta=document.createElement('textarea');
                ta.value=text; ta.setAttribute('readonly','');
                ta.style.cssText='position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
                document.body.appendChild(ta);
                ta.focus(); ta.select();
                try{ ta.setSelectionRange(0, ta.value.length); }catch(e){}
                var ok=false;
                try{ ok=document.execCommand('copy'); }catch(e){ ok=false; }
                document.body.removeChild(ta);
                resolve(!!ok);
            }catch(e){ resolve(false); }
        }
        try{
            if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(text).then(function(){resolve(true);}, legacy); }
            else legacy();
        }catch(e){ legacy(); }
    });
};
function showLoader(){try{if(window.RASDLoading){window.RASDLoading.show();return;}}catch(e){}var l=document.getElementById('page-loader');if(!l)return;l.classList.remove('hidden');requestAnimationFrame(function(){l.classList.add('is-visible');l.setAttribute('aria-hidden','false');});}
function hideLoader(){try{if(window.RASDLoading){window.RASDLoading.reset();return;}}catch(e){}var l=document.getElementById('page-loader');if(!l)return;l.classList.remove('is-visible');l.setAttribute('aria-hidden','true');setTimeout(function(){l.classList.add('hidden');},250);}
window.showLoader=showLoader;window.hideLoader=hideLoader;

document.addEventListener('DOMContentLoaded', function(){
    document.body.style.overflow='';
    hideLoader();
});

async function ajaxSubmit(form, onSuccess) {
    const btn = form.querySelector('button[type="submit"]:not([formnovalidate])');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>'; }
    try {
        const fd = new FormData(form);
        const res = await fetch(form.action, {
            method: form.method || 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        });
        const data = await res.json();
        if (res.ok && data.success) {
            if (onSuccess) onSuccess(data);
            else if (data.redirect) window.location.href = data.redirect;
            else location.reload();
        } else {
            if (data.errors) {
                const first = Object.values(data.errors)[0];
                window.toast(Array.isArray(first) ? first[0] : first);
            } else {
                window.toast(data.message || window.RASD_I18N.errorOccurred);
            }
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch(e) {
        form.submit();
    }
}

async function ajaxFilter(url) {
    const container = document.getElementById('notes-list');
    if (!container) return window.location.href = url;
    container.style.opacity = '0.5';
    try {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newList = doc.getElementById('notes-list');
        const newTabs = doc.querySelector('.tabs-container');
        if (newList) container.innerHTML = newList.innerHTML;
        if (newTabs) document.querySelector('.tabs-container').innerHTML = newTabs.innerHTML;
        container.style.opacity = '1';
        history.pushState(null, '', url);
    } catch(e) { window.location.href = url; }
}
window.ajaxSubmit = ajaxSubmit;
window.ajaxFilter = ajaxFilter;
