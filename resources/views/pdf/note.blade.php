<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<style>
  @page { size: A4; margin: 14mm 13mm 18mm 13mm; }
  * { box-sizing: border-box; }
  html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  /* الخط أكبر من النسخة السابقة (12.5px ← 14.5px): الوثيقة تُقرأ من ورقة
     مطبوعة أو من يد مراقب، لا من شاشة قريبة. */
  body {
    font-family: 'Noto Naskh Arabic', 'Amiri', 'DejaVu Sans', 'Segoe UI', Tahoma, sans-serif;
    color: #16261b; font-size: 14.5px; line-height: 1.85; margin: 0;
  }

  /* آخر عنصر في التدفق بلا هامش سفلي: هامشه كان يدفع صفحة فارغة
     أخيرة (عطل قائم: صفحة 2 فارغة إلا التذييل). */
  body > :last-child { margin-bottom: 0 !important; }

  /* ---------- الترويسة ---------- */
  .np-head { text-align: center; }
  .np-ministry { font-weight: 800; font-size: 20px; letter-spacing: .2px; }
  .np-ministry-sub { font-weight: 700; font-size: 15px; color: #0e6a38; }
  .np-rule { border: 0; border-top: 3px double #0e6a38; margin: 10px 0 14px; }

  .np-titlebar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; background: #eef4f0; border: 1px solid #c9d8cd;
    border-radius: 10px; padding: 9px 14px; margin-bottom: 12px;
  }
  .np-title { font-size: 18px; font-weight: 800; }
  .np-status {
    display: inline-block; color: #fff; border-radius: 999px;
    padding: 2px 15px; font-weight: 700; font-size: 13px; white-space: nowrap;
  }
  /* لون الشارة حسب الحالة — كانت خضراء دائماً حتى للمرفوضة. */
  .np-status.ok { background: #0e6a38; }
  .np-status.wait { background: #b7791f; }
  .np-status.bad { background: #b42323; }
  .np-status.muted { background: #5a6a5e; }

  /* ---------- جدول التفاصيل: صفّان في كل سطر لاستخدام عرض الورقة ---------- */
  table.np-meta { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 14px; }
  table.np-meta th, table.np-meta td {
    border: 1px solid #c9d2c9; padding: 6px 10px; text-align: start; font-size: 13.5px;
  }
  table.np-meta th { background: #eef4f0; width: 20%; font-weight: 700; color: #23402c; }
  table.np-meta td { background: #fff; }
  /* خلايا الحشو (صف فردي أخير) تنهار بلا حدود — كانت تظهر صفاً فارغاً مؤطراً. */
  table.np-meta th:empty, table.np-meta td:empty {
    border: 0; background: transparent; padding: 0;
  }

  /* ---------- الأقسام ---------- */
  .np-sec {
    font-size: 15.5px; font-weight: 800; margin: 18px 0 9px; color: #0e6a38;
    border-inline-start: 5px solid #0e6a38; padding-inline-start: 10px;
    break-after: avoid;
  }

  .np-desc p {
    margin: 0 0 9px; line-height: 2.05; font-size: 15px;
    text-align: justify; text-justify: inter-word;
    orphans: 2; widows: 2;
  }
  /* الفقرة اللاحقة تُزاح قليلاً لتمييز بدايتها — عادة طباعة عربية رسمية. */
  .np-desc p + p { text-indent: 7mm; }
  .np-desc p:last-child { margin-bottom: 0; }
  .np-desc-empty { color: #7e897e; font-style: italic; }

  /* ---------- المرفقات: بطاقة لكل مرفق ---------- */
  /* البطاقة تُقسم بين صفحتين إن لزم (break-inside: avoid كان يمنع القسمة
     فيترك نصف صفحة فارغة، وهو أسوأ شكل: فراغ كبير ثم بطاقة في صفحة تالية).
     الآن يُقسم المحتوى، ويُمنع فقط رأس البطاقة عن الانفصال عن صورته. */
  .np-att {
    border: 1px solid #dfe5df; border-radius: 10px; padding: 10px 12px;
    margin-bottom: 11px; background: #fdfefd; break-inside: auto;
  }
  .np-att:last-child { margin-bottom: 0; }
  .np-att-head { display: flex; align-items: baseline; gap: 8px; margin-bottom: 6px;
    break-inside: avoid; break-after: avoid; }
  .np-att-no {
    display: inline-block; min-width: 24px; text-align: center;
    background: #0e6a38; color: #fff; border-radius: 6px;
    font-weight: 700; font-size: 13px; padding: 1px 7px;
  }
  /* أسماء المرفقات مخالطة (عربية + .png مثلاً). قِسناها في Chrome:
     unicode-bidi:plaintext ينعكس امتداد الملف إلى أوّل الاسم فيقرأ
     «png.1 لقطة الكاميرا»، و isolate-ltr هو الذي يُبقي الاسم كما هو.
     والعزل يمنع الاسم من التأثير في ما حوله. */
  .np-att-name {
    font-weight: 700; font-size: 14px; word-break: break-word;
    direction: ltr; unicode-bidi: isolate;
  }
  /* المقاس والنوع لاتينيان («image/png · 1.2 KB») فيعزلان بالسبب نفسه. */
  .np-att-meta {
    color: #5a6a5e; font-size: 12.5px; margin-inline-start: auto;
    white-space: nowrap; direction: ltr; unicode-bidi: isolate;
  }
/* الصورة بحجمها الطبيعي ممركزة، وسقف ارتفاع 200mm يعني أن صورة كبيرة
     تملأ الصفحة تقريباً بدل ترك فراغ أسفلها (118mm كان يترك ~120mm فارغة).
     max-width يحمي من التجاوز أفقياً، وobject-fit يمنع التشويه. */
  .np-att img {
    display: block; width: auto; height: auto;
    max-width: 100%; max-height: 200mm;
    margin: 8px auto 2px; border: 1px solid #e6ebe6; border-radius: 8px;
    object-fit: contain; break-inside: avoid;
  }
  .np-video-badge {
    display: inline-block; background: #16261b; color: #fff; border-radius: 6px;
    padding: 1px 11px; font-size: 12.5px; margin-top: 6px;
  }
  /* تصريح اللقطة: القارئ يعرف أنه يرى صورة من فيديو، لا الفيديو. */
  .np-video-note { color: #5a6a5e; font-size: 11.5px; margin-top: 5px;
    break-inside: avoid; }
  .np-att-missing, .np-att-corrupt { color: #b42323; font-weight: 700; }
  /* تصريح صريح عند التصغير: لا نصوّر الأصل بلا أن يقول القارئ. */
  .np-att-scaled { color: #8a6d3b; font-size: 11.5px; margin-top: 4px; }

  /* الرابط: مرئي وكامل وقابل للنقر. اتجاهه لاتيني ومحارفه تكسر عند
     الشرطات، فلا تنعكس الروابط ولا يتمدّد عنوان طويل خارج الهامش. */
  /* الرابط: يُعرض مقروءاً لا مقطوعاً.
     الرابط الموقّع طويل (توقيع 32 خانة)، وword-break:break-all كان يقطعه
     في منتصف التوقيع فيبدو مبتوراً وغير قابل للنسخ. بدل ذلك نُظهر المسار
     فقط في النص، ونجعل العنصر كله区域 قابلة للنقر على الرابط الكامل. */
.np-link {
    display: inline-block; margin-top: 7px; padding: 3px 10px;
    font-size: 11.5px; line-height: 1.7;
    color: #0e6a38; text-decoration: none;
    border: 1px solid #c9d8cd; border-radius: 7px; background: #f4f8f5;
    direction: ltr; text-align: left; unicode-bidi: embed;
    word-break: normal; overflow-wrap: anywhere;
  }
  .np-link::before { content: none; }
  /* النص المعروض: مسار المرفق فقط — التوقيع معامل تقني لا يعين القارئ. */
  .np-link .np-link-label { unicode-bidi: plaintext; }
  /* الأيقونة تسبق النص بصرياً في كل اتجاه: bidi داخل .np-link يعكس
     ترتيب (أيقونة + نص) في RTL فتقفز الأيقونة لآخر السطر. */
  .np-link .np-link-icon { unicode-bidi: isolate; }

  .np-empty { color: #7e897e; }

  /* ---------- التذييل ----------
     كان position:fixed مع bottom سالب (-13mm) لتجاوز هامش @page.
     هذا ما كان يولّد صفحة أخيرة فارغة: العنصر الثابت يُعاد على كل صفحة،
     وحين لا يتسع محتواه للصفحة الأخيرة دفعها لصفحة جديدة فارغة.
     الحل: ثابت بلا إزاحة سالبة، داخل حدود الصفحة.

     رقم الصفحة: counter(page) داخل عنصر fixed يُقيَّم صفراً في Chrome
     (فكان يطبع «صفحة 0» — عطل قائم). Chrome CLI لا يقبل قوالب تذييل
     مخصصة، و@page margin boxes غير مدعومة، فلا رقم صفحة صحيح هنا.
     فالتذييل يعرض مصدر الوثيقة بدل رقمٍ كاذب. */
  .np-footer {
    position: fixed; bottom: 0; left: 0; right: 0; text-align: center;
    font-size: 10.5px; color: #7e897e; border-top: 1px solid #e6ebe6;
    padding-top: 3px; background: #fff;
  }
  /* الطابع الزمني يخلط أرقاماً لاتينية بشرطات، فانعكس بلا عزل وظهر
     «9 / 0 2026-10-09». العزل اللاتيني يرتّبه صحيحاً. */
  .np-footer time { direction: ltr; unicode-bidi: isolate; }
</style>
</head>
<body>
  <div class="np-head">
    <div class="np-ministry">{{ $ministry }}</div>
    <div class="np-ministry-sub">{{ $ministrySub }}</div>
  </div>
  <hr class="np-rule">

  <div class="np-titlebar">
    <span class="np-title">{{ $kindTitle }}</span>
    <span class="np-status {{ $statusClass ?? 'muted' }}">{{ $statusLabel }}</span>
  </div>

  <table class="np-meta">
    @foreach (array_chunk($meta, 2) as $pair)
      <tr>
        @if (count($pair) === 2)
          <th>{{ $pair[0][0] }}</th><td>{{ $pair[0][1] }}</td>
          <th>{{ $pair[1][0] }}</th><td>{{ $pair[1][1] }}</td>
        @else
          <th>{{ $pair[0][0] }}</th><td colspan="3">{{ $pair[0][1] }}</td>
        @endif
      </tr>
    @endforeach
  </table>

  <div class="np-sec">{{ $t['description'] }}</div>
  <div class="np-desc">
    @forelse ($paragraphs as $para)
      <p>{{ $para }}</p>
    @empty
      <p class="np-desc-empty">—</p>
    @endforelse
  </div>

  <div class="np-sec">{{ str_replace(':count', $attachmentsCount, $t['attachmentsCount']) }}</div>
  @if (count($items) === 0)
    <div class="np-empty">{{ $t['noAttachments'] }}</div>
  @endif

  @foreach ($items as $it)
    <div class="np-att">
      <div class="np-att-head">
        <span class="np-att-no">{{ $it['index'] }}</span>
        <span class="np-att-name">{{ $it['name'] }}</span>
        <span class="np-att-meta">{{ $it['meta'] }}</span>
      </div>

      @if (!empty($it['dataUri']))
        <img src="{{ $it['dataUri'] }}">
        @if (empty($it['isOriginal']))
        <div class="np-att-scaled">{{ $t['imageScaled'] }}</div>
        @endif
      @endif

      @if ($it['kind'] === 'video')
        <div class="np-video-note">
          <span class="np-video-badge">▶ {{ $t['videoFrame'] }}</span>
          {{ empty($it['dataUri']) ? $t['videoNoFrame'] : $t['videoSnapshot'] }}
        </div>
      @endif

      @if ($it['kind'] === 'missing')
        <div class="np-att-missing">✕ {{ $t['fileMissing'] }}</div>
      @endif

      @if ($it['kind'] === 'too_large')
        <div class="np-att-scaled">{{ $t['imageTooLarge'] }} — {{ $it['name'] }}</div>
      @endif

      @if ($it['kind'] === 'corrupt')
        <div class="np-att-corrupt">⚠ {{ $it['name'] }} — ملف تالف لا يمكن عرضه (الأصل متاح عبر الرابط أدناه)</div>
      @endif

      @if (!empty($it['url']))
        <a class="np-link" href="{{ $it['url'] }}">
          <span class="np-link-icon">🔗</span>
          <span class="np-link-label">{{ $t['openAttachment'] }} · {{ $it['name'] }}</span>
        </a>
      @endif
    </div>
  @endforeach

  <div class="np-footer">{{ $ministrySub }} · <time>{{ $generatedAt }}</time></div>
</body>
</html>
