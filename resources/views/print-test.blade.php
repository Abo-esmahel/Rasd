<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>Print Isolation Test</title>
<style>
  @page { size: A4 portrait; margin: 0; }
  html, body { margin:0; padding:0; background:#f0f0f0; }
  .test-page {
    width:210mm; height:297mm; background:white; position:relative;
    margin: 10mm auto; border:1px solid #ddd; box-sizing:border-box;
  }
  @media print {
    html, body { background:white !important; margin:0 !important; padding:0 !important; }
    body { visibility:hidden !important; }
    #test-root, #test-root * { visibility:visible !important; }
    #test-root { position:absolute !important; left:0 !important; top:0 !important; width:100% !important; }
    .test-page { border:none !important; box-shadow:none !important; margin:0 !important; }
    @page { size:A4 portrait; margin:0; }
  }
</style>
</head>
<body>
<div style="padding:10mm; font-family:sans-serif; text-align:center;">
  <h2>اختبارات العزل — شغّل كل اختبار واطبع</h2>
  <button onclick="runTest('A')" style="padding:8px 16px; margin:4px;">TEST A — Empty A4</button>
  <button onclick="runTest('B')" style="padding:8px 16px; margin:4px;">TEST B — Black Rectangle</button>
  <button onclick="runTest('C')" style="padding:8px 16px; margin:4px;">TEST C — Single Image</button>
  <button onclick="runTest('D')" style="padding:8px 16px; margin:4px;">TEST D — Real Layout</button>
  <button onclick="window.print()" style="padding:8px 16px; margin:4px; background:#0e6a38; color:white;">طباعة</button>
</div>

<div id="test-root" style="display:flex; justify-content:center; padding:10mm;">
  <div class="test-page" id="test-page">
    <div style="position:absolute; left:6mm; top:6mm; right:6mm; bottom:6mm; border:1px dashed #ccc; display:flex; align-items:center; justify-content:center; color:#999;">اختر اختباراً</div>
  </div>
</div>

<script>
function runTest(t){
  const page = document.getElementById('test-page');
  page.innerHTML = '';
  if(t==='A'){
    page.innerHTML = '<div style="position:absolute; left:0; top:0; width:210mm; height:297mm; background:white;"></div>';
  } else if(t==='B'){
    page.innerHTML = '<div style="position:absolute; left:0; top:0; width:210mm; height:297mm; background:black;"></div>';
  } else if(t==='C'){
    page.innerHTML = '<img src="https://picsum.photos/800/600" style="position:absolute; left:10mm; top:10mm; width:190mm; height:130mm; object-fit:fill; display:block;" onload="console.log(\"C loaded\", this.naturalWidth)">';
  } else if(t==='D'){
    // Real layout: use actual note image if available
    fetch('/notes', {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.text()).then(html=>{
      console.log('fetched notes length', html.length);
      alert('افتح نافذة طباعة الملاحظة الحقيقية للمقارنة');
    });
  }
  console.log('Test',t,'— افتح Print Preview الآن');
  setTimeout(()=> window.print(), 300);
}
window.debugPrint = function(){
  const r = document.getElementById('test-page').getBoundingClientRect();
  console.log('test-page rect', r.width, r.height, r.width/3.77953+'mm', r.height/3.77953+'mm');
  console.log('mmPx', document.createElement('div').getBoundingClientRect.width);
}
</script>
</body>
</html>
