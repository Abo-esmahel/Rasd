# نشر RASD على سيرفر جديد (DevOps)

قائمة تحقق لنشر نسخة مطابقة دون تعديل الكود. كل البنود تحققت من الكود الحالي.

## 1) متطلبات السيرفر

| المكوّن | المطلوب | ملاحظة |
|---|---|---|
| PHP | `^8.4` (`composer.json`) | الإضافات تُفحص تلقائياً عند `composer install` ومنها `ext-gd` (مصغّرات/PDF) و`ext-zip` و`ext-pdo_sqlite` |
| Node | 20+ | لبناء الواجهة فقط (`npm run build` — يحتاج إنترنت وقت البناء لخطوط Bunny) |
| Chrome/Chromium | للطباعة عبر `/api/print-pdf` | أو حدّد `CHROME_BIN` — بدونه تفشل الطباعة من الجوال بخطأ واضح |
| ffmpeg | اختياري | لمصغّرات الفيديو (`FFMPEG_BIN`) — البديل شارة نصية تلقائياً |
| قاعدة البيانات | sqlite افتراضياً (ملف واحد — لسيرفر واحد) | لعدة سيرفرات: MySQL + تخزين مشترك + Redis (إعداد يدوي) |

## 2) خطوات النشر

```bash
git clone <repo> && cd Rasd
composer setup            # install + .env + key + sqlite + migrate + npm build
php artisan storage:link  # ضروري لصور الحسابات (قرص public)
# عدّل .env (القسم 3)، ثم:
php artisan migrate --force
# عامل الطوابير (إشعارات/ترجمة/دفع) — supervisor/systemd:
php artisan queue:work --sleep=3 --tries=1
```

وجّه خادم الويب إلى `public/`. لا حاجة لـ cron (لا مهام مجدولة اليوم).

## 3) متغيرات البيئة لكل نشرة

| المتغير | إلزامي؟ | الأثر إن غاب |
|---|---|---|
| `APP_URL` | نعم (عنوان LAN الحقيقي، لا `localhost`) | روابط المشاركة + `Storage::url` + اكتشاف الجوال |
| `APP_KEY`, `JWT_SECRET` | نعم | رفض إقلاع/مصادقة |
| `SEED_DEMO_PASSWORD` | عند البذر فقط | كلمة مرور مستخدمي التجارب (لا تبذر على الإنتاج) |
| `VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY/VAPID_SUBJECT` | لدفع المتصفح فقط | يُعطَّل الدفع silently بدونه |
| `GEMINI_API_KEY` (+`AI_ENABLED=true`) | للذكاء فقط | رجوع تلقائي للصياغة اليدوية |
| `MAIL_*` | للبريد فقط | الافتراضي `log` (لا إرسال) |
| `CHROME_BIN`, `FFMPEG_BIN` | عند غياب المسارات الشائعة | اكتشاف تلقائي من PATH أولاً |

## 4) تطبيق الجوال

```bash
# الافتراضات تكفي للشبكة الحالية؛ لسيرفر جديد:
flutter build apk --release \
  --dart-define=RASD_FALLBACK_URL=http://NEW-IP:8000 \
  --dart-define=RASD_PORT=8000
```

التطبيق يكتشف السيرفر تلقائياً (محفوظ → gateway → مسح بصمة `/api/ping`)،
والقيم المثبتة للطوارئ فقط. يعمل عبر `http` على LAN (`usesCleartextTraffic`).

## 5) ممنوعات الإنتاج

- `php artisan db:seed --class=RasdSmartSeed` و`migrate:fresh --seed` **يمسحان بيانات العمل** — للتطوير فقط.
- `APP_DEBUG=true` ممنوع (يكشف المسارات).
- مستخدمو البذور (`tariq`/`writer`…) بكلمة `password` — دوّرها عبر `SEED_DEMO_PASSWORD` قبل أي نشر تجريبي.
