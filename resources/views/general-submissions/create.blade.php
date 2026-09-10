@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <a href="{{ route('general-submissions.index') }}" class="w-9 h-9 rounded-lg bg-white border border-[#e6e9e1] flex items-center justify-center text-ink-400 hover:text-ink-700 transition">‹</a>
        <div>
            <h1 class="text-xl font-extrabold text-ink-800">إرسال عام جديد</h1>
            <p class="text-sm text-ink-400">اختر كاتبًا واحدًا أو عدة كتّاب لإرسال الملاحظة</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-[#e6e9e1] p-6">
        <form method="POST" action="{{ route('general-submissions.store', [], false) }}" enctype="multipart/form-data" class="space-y-5" id="gs-create-form" data-no-loader novalidate>
            @csrf
            <div id="gs-form-errors" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>
            <div id="gs-upload-progress" class="hidden p-4 bg-[#eef4f0] border border-[#cde7d6] rounded-xl">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold text-[#0e6a38] flex items-center gap-2"><span class="w-3 h-3 border-2 border-[#0e6a38] border-t-transparent rounded-full animate-spin"></span>جاري الرفع...</span>
                    <span id="gs-upload-progress-text" class="text-xs font-bold text-ink-500">0%</span>
                </div>
                <div class="w-full bg-white rounded-full h-2.5 border border-[#e6e9e1] overflow-hidden">
                    <div id="gs-upload-progress-bar" class="h-2.5 rounded-full bg-[#0e6a38] transition-all duration-300" style="width:0%"></div>
                </div>
                <div id="gs-upload-progress-detail" class="mt-1 text-[11px] text-ink-400">جاري رفع الملفات الأصلية دون تعديل (الجودة 100% محفوظة) — لا تغلق الصفحة</div>
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-700 mb-1.5">الوصف <span class="text-red-500">*</span></label>
                <textarea name="description" rows="4" required maxlength="5000" class="block w-full rounded-xl border border-[#e6e9e1] bg-white p-4 text-sm leading-7 focus:border-[#0e6a38] focus:ring-2 focus:ring-[#0e6a38]/10 outline-none @error('description') border-red-400 @enderror" placeholder="صف ما تم رصده بدقة...">{{ old('description') }}</textarea>
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-700 mb-2">كتّاب التقارير <span class="text-red-500">*</span> <span class="text-xs font-normal text-ink-400">— اختر واحدًا أو أكثر</span></label>
                <div class="rounded-xl border border-[#e6e9e1] bg-[#f5f7f5] p-4">
                    @if($writers->isEmpty())
                        <p class="text-sm text-ink-400">لا يوجد كتّاب تقارير حاليًا</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($writers as $writer)
                                <label class="flex items-center gap-3 p-3 rounded-xl bg-white border border-[#e6e9e1] hover:border-[#0e6a38] cursor-pointer transition has-[:checked]:border-[#0e6a38] has-[:checked]:bg-[#eef4f0]">
                                    <input type="checkbox" name="report_writer_ids[]" value="{{ $writer->id }}" {{ in_array($writer->id, old('report_writer_ids', [])) ? 'checked' : '' }} class="w-4 h-4 rounded border-[#e6e9e1] text-[#0e6a38] focus:ring-[#0e6a38]">
                                    <span class="text-sm font-bold text-ink-700">{{ $writer->name }}</span>
                                    <span class="text-xs text-ink-400 mr-auto">{{ '@' . $writer->username }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                @error('report_writer_ids') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                @error('report_writer_ids.*') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-700 mb-1.5">المرفقات <span class="text-ink-300 font-medium text-xs">— اختياري (صور / فيديو / صوت)</span></label>
                <div class="rounded-xl border-2 border-dashed border-[#e6e9e1] bg-[#f5f7f5] hover:border-[#0e6a38] transition p-5 text-center" id="gs-drop-zone">
                    <label for="gs-files" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#0e6a38] text-white font-bold text-sm cursor-pointer hover:bg-[#0a4d28] transition">اختيار ملفات</label>
                    <button type="button" id="gs-cam-photo-btn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 font-bold text-sm hover:border-[#0e6a38] hover:text-[#0e6a38] transition">📷 تصوير</button>
                    <button type="button" id="gs-cam-video-btn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white border border-[#e6e9e1] text-ink-700 font-bold text-sm hover:border-[#0e6a38] hover:text-[#0e6a38] transition">🎥 فيديو</button>
                    <input type="file" id="gs-cam-photo-input" accept="image/*" capture="environment" class="hidden">
                    <input type="file" id="gs-cam-video-input" accept="video/*" capture="environment" class="hidden">
                    <input type="file" id="gs-files" name="files[]" multiple accept="image/*,video/*,audio/*,.aac,.m4a,.mp3,.wav,.ogg,.flac,.opus,.wma,.aiff,.amr,.3ga,.weba" class="hidden">
                    <div id="gs-file-list" class="mt-3 hidden text-right space-y-1.5"></div>
                </div>
                @error('files') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 py-3 rounded-xl bg-[#0e6a38] hover:bg-[#0a4d28] text-white font-bold text-sm transition">إرسال إلى المختارين</button>
                <a href="{{ route('general-submissions.index') }}" class="px-6 py-3 rounded-xl bg-white border border-[#e6e9e1] text-ink-600 font-bold text-sm hover:bg-[#f5f7f5] transition">إلغاء</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const input=document.getElementById('gs-files'),zone=document.getElementById('gs-drop-zone'),list=document.getElementById('gs-file-list');
    const formEl=document.getElementById('gs-create-form'),errEl=document.getElementById('gs-form-errors');
    let transfer=new DataTransfer(),intended=0;
    let pendingGs=[];
    function setGsProgress(pct, detail){
        const wrap=document.getElementById('gs-upload-progress'), bar=document.getElementById('gs-upload-progress-bar'), txt=document.getElementById('gs-upload-progress-text'), det=document.getElementById('gs-upload-progress-detail');
        if(!wrap) return;
        wrap.classList.remove('hidden');
        if(bar) bar.style.width=pct+'%';
        if(txt) txt.textContent=Math.round(pct)+'%';
        if(det && detail) det.textContent=detail;
    }
    function hideGsProgress(){ const w=document.getElementById('gs-upload-progress'); if(w) w.classList.add('hidden'); }
    function xhrUploadGs(url, fd, csrf, onProgress){
        return new Promise((resolve, reject)=>{
            const xhr=new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Accept','application/json');
            xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
            if(csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.timeout=600000;
            if(xhr.upload && onProgress) xhr.upload.onprogress=(e)=>{ if(e.lengthComputable){ const pct=Math.round(e.loaded/e.total*100); onProgress(pct, e.loaded, e.total); } };
            xhr.onload=()=>{ let data=null; try{ data=JSON.parse(xhr.responseText);}catch(_){} resolve({status:xhr.status, ok:xhr.status>=200&&xhr.status<300, data, raw:xhr.responseText}); };
            xhr.onerror=()=>reject(new Error('فشل الشبكة'));
            xhr.ontimeout=()=>reject(Object.assign(new Error('انتهت مهلة الإرسال (10 دقائق)'),{name:'AbortError'}));
            xhr.send(fd);
        });
    }
    const MAX_FILES={{ max(1, (int) ini_get('max_file_uploads') ?: 20) }};
    function sync(){ try{ input.files=transfer.files; }catch(e){} }
    function render(){
        const files=Array.from(transfer.files);
        if(!files.length){ list.classList.add('hidden'); list.innerHTML=''; return; }
        list.classList.remove('hidden');
        const counters={img:0,vid:0,aud:0,other:0};
        const escAttr=s=>String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
        const friendlyName=f=>{
            if(f.type.startsWith('audio/')) return 'مقطع صوتي '+ (++counters.aud);
            if(f.type.startsWith('video/')) return 'فيديو '+ (++counters.vid);
            if(f.type.startsWith('image/')) return 'صورة '+ (++counters.img);
            const ext=(f.name.split('.').pop()||'').toLowerCase();
            return 'مرفق '+ (++counters.other) + (ext && ext.length<=5 ? ' (.'+ext+')' : '');
        };
        list.innerHTML=files.map((f,i)=>{
            const sz=(f.size/1024/1024).toFixed(2)+' MB';
            return `<div class="flex items-center gap-2.5 p-2.5 bg-white border border-[#e6e9e1] rounded-lg text-sm"><div class="flex-1 min-w-0 text-right"><div class="font-bold text-ink-700 truncate" title="${escAttr(f.name)}">${friendlyName(f)}</div><div class="text-xs text-ink-400">${sz} • ${f.type||'—'}</div></div><button type="button" data-rm="${i}" class="shrink-0 w-7 h-7 rounded-lg hover:bg-red-50 text-ink-300 hover:text-red-500">✕</button></div>`;
        }).join('');
        list.querySelectorAll('[data-rm]').forEach(b=>b.addEventListener('click',()=>{
            const idx=parseInt(b.dataset.rm),dt=new DataTransfer();
            const removedFile=Array.from(transfer.files)[idx];
            Array.from(transfer.files).forEach((f,j)=>{ if(j!==idx) dt.items.add(f); });
            transfer=dt; intended=Math.max(0,intended-1);
            if(removedFile) pendingGs=pendingGs.filter(f=>f!==removedFile);
            else pendingGs.splice(idx,1);
            sync(); render();
        }));
    }
    function addFiles(arr){
        for(const file of arr){
            if(transfer.files.length>=MAX_FILES){ alert('الحد الأقصى '+MAX_FILES+' ملف'); break; }
            const ext=file.name.split('.').pop().toLowerCase();
            const audioExts=['mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','weba'];
            const isAudio=audioExts.includes(ext)||file.type.startsWith('audio/');
            if(!['jpg','jpeg','png','webp','mp4','webm','mov','avi','3gp','mkv','m4v','mpg','3gpp'].includes(ext)&&!file.type.startsWith('image/')&&!file.type.startsWith('video/')&&!isAudio){ alert('نوع غير مدعوم: '+file.name); continue; }
            if(file.type.startsWith('image/')&&file.size>20*1024*1024){ alert('حجم الصورة كبير (20MB): '+file.name); continue; }
            if(file.type.startsWith('video/')&&file.size>100*1024*1024){ alert('حجم الفيديو كبير (100MB): '+file.name); continue; }
            if(isAudio&&file.size>100*1024*1024){ alert('حجم الصوت كبير (100MB): '+file.name); continue; }
            transfer.items.add(file); pendingGs.push(file); intended++;
        }
        sync(); render();
    }
    input?.addEventListener('change',e=>{ addFiles(Array.from(e.target.files)); input.value=''; sync(); });
    const camPhoto=document.getElementById('gs-cam-photo-input'),camVideo=document.getElementById('gs-cam-video-input');
    document.getElementById('gs-cam-photo-btn')?.addEventListener('click',()=>camPhoto?.click());
    document.getElementById('gs-cam-video-btn')?.addEventListener('click',()=>camVideo?.click());
    camPhoto?.addEventListener('change',e=>{ addFiles(Array.from(e.target.files)); camPhoto.value=''; sync(); });
    camVideo?.addEventListener('change',e=>{ addFiles(Array.from(e.target.files)); camVideo.value=''; sync(); });
    ['dragenter','dragover'].forEach(ev=>zone?.addEventListener(ev,e=>{e.preventDefault();}));
    zone?.addEventListener('drop',e=>{ e.preventDefault(); if(e.dataTransfer?.files?.length) addFiles(Array.from(e.dataTransfer.files)); });
    formEl?.addEventListener('submit',async e=>{
        if(!(transfer.files.length>0||intended>0)) return; 
        e.preventDefault();
        errEl.classList.add('hidden');
        const btn=e.submitter||document.activeElement;
        if(btn) btn.disabled=true;
        const fd=new FormData(formEl);
        fd.delete('files[]');
        const toSend=transfer.files.length>0?Array.from(transfer.files):(pendingGs.length>0?pendingGs.slice():Array.from(input.files));
        toSend.forEach(f=>fd.append('files[]',f));
        const announced=intended;
        
        const totalBytesGs = toSend.reduce((s,f)=>s+f.size,0);
        if(totalBytesGs > 120*1024*1024){
            errEl.innerHTML='<div class="font-bold mb-1 text-red-600">إجمالي المرفقات كبير جداً</div><p class="text-xs">الحجم الإجمالي '+(totalBytesGs/1024/1024).toFixed(1)+' MB يتجاوز الحد 128M. قلل المرفقات ثم أعد المحاولة.</p>';
            errEl.classList.remove('hidden');
            errEl.scrollIntoView({behavior:'smooth',block:'center'});
            if(btn) btn.disabled=false;
            hideGsProgress();
            return;
        }
        fd.append('client_files_count',String(announced));
        try{
            const csrfTokenGs=document.querySelector('meta[name="csrf-token"]')?.content || '';
            setGsProgress(5, 'جاري رفع '+toSend.length+' ملف...');
            document.getElementById('gs-upload-progress')?.scrollIntoView({behavior:'smooth',block:'center'});
            const res=await xhrUploadGs(formEl.action, fd, csrfTokenGs, (pct, loaded, total)=> setGsProgress(Math.max(5, Math.min(95, pct)), 'جاري الرفع '+pct+'%'+ (loaded ? ' ('+(loaded/1024/1024).toFixed(1)+' / '+(total/1024/1024).toFixed(1)+' MB)' : '')+' — لا تغلق الصفحة'));
            setGsProgress(98, 'تم الرفع 100%، جاري التحقق من الحفظ...');
            let data=res.data
            const fail=(t,errs)=>{
                hideGsProgress();
                const list=(errs&&errs.length?errs:['فشل رفع المرفقات. لم يتم حفظ الإرسالية.']).map(x=>typeof x==='string'?x:((x.file?x.file+': ':'')+(x.message||JSON.stringify(x)))).join('<br>');
                errEl.innerHTML='<div class="font-bold mb-1 text-red-600">'+t+'</div><p class="text-xs">'+list+'</p><p class="mt-2 text-xs font-bold">الملفات محفوظة — أعد المحاولة.</p>';
                errEl.classList.remove('hidden'); errEl.scrollIntoView({behavior:'smooth',block:'center'});
                if(btn) btn.disabled=false;
            };
            if(data&&data.success===false){ fail('فشل رفع المرفقات. لم يتم حفظ الإرسالية.',data.attachment_errors); return; }
            if(data&&typeof data.files_received==='number'&&typeof data.attachments_saved==='number'){
                if(data.files_received!==data.attachments_saved){ fail('فشل رفع المرفقات. لم يتم حفظ الإرسالية.',data.attachment_errors); return; }
                if((data.attachment_errors||[]).length>0){ fail('فشل رفع المرفقات. لم يتم حفظ الإرسالية.',data.attachment_errors); return; }
            }
            if(res.ok&&data&&data.success!==false){ window.location.href="{{ route('general-submissions.index', [], false) }}"; return; }
            if(res.status===422){
                if(data&&(data.attachment_errors||data.success===false)){ fail('فشل رفع المرفقات. لم يتم حفظ الإرسالية.',data.attachment_errors); return; }
                const errs=(data&&data.errors)||{}; let html='<div class="font-bold mb-1">يرجى تصحيح الحقول:</div><ul class="list-disc list-inside space-y-1">';
                for(const [k,ms] of Object.entries(errs)) for(const m of ms) html+=`<li>${m}</li>`;
                html+='</ul>'; errEl.innerHTML=html; errEl.classList.remove('hidden');
            } else { fail('فشل الإرسال (كود: '+res.status+')',[]); }
        }catch(err){
            hideGsProgress();
            if(err.name === 'AbortError'){
                errEl.innerHTML='<div class="font-bold mb-1 text-red-600">انتهت مهلة الإرسال (10 دقائق)</div><p class="text-xs">تحقق من اتصالك أو قلل حجم المرفقات.</p>';
            } else {
                errEl.innerHTML='<div class="font-bold text-red-600">خطأ في الشبكة</div><p class="text-xs">'+(err.message||'')+'</p>';
            }
            errEl.classList.remove('hidden');
        }finally{ hideGsProgress(); if(btn) btn.disabled=false; }
    });
})();
</script>
@endpush
@endsection
