#!/bin/sh
set -eu
cd /var/www/html

# 1) ملف .env — من المثال إن غاب (يحمل معه APP_URL الافتراضي)
if [ ! -f .env ]; then
  echo "[rasd] creating .env from .env.example"
  cp .env.example .env
fi

# 2) مزامنة APP_URL من بيئة الحاوية (docker-compose يمرر عنوان LAN الحقيقي)
if [ -n "${APP_URL:-}" ]; then
  # استبدل سطر APP_URL أو أضفه
  if grep -q '^APP_URL=' .env; then
    sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
  else
    echo "APP_URL=${APP_URL}" >> .env
  fi
fi

# 3) مفاتيح الإقلاع — التطبيق يرفض العمل بدونها
if grep -q '^APP_KEY=$' .env || ! grep -q '^APP_KEY=.\+' .env; then
  echo "[rasd] generating APP_KEY"
  php artisan key:generate --force
fi
if ! grep -q '^JWT_SECRET=.\{32,\}' .env; then
  echo "[rasd] generating JWT_SECRET"
  SECRET=$(php -r "echo bin2hex(random_bytes(32)).PHP_EOL;")
  if grep -q '^JWT_SECRET=' .env; then
    sed -i "s|^JWT_SECRET=.*|JWT_SECRET=${SECRET}|" .env
  else
    echo "JWT_SECRET=${SECRET}" >> .env
  fi
fi

# 4) قاعدة SQLite — ملف واحد دائم عبر volume
if [ ! -f database/database.sqlite ]; then
  echo "[rasd] creating database.sqlite"
  touch database/database.sqlite
fi

# 5) صلاحيات + رابط التخزين العام (صور الحسابات)
mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R 775 storage bootstrap/cache || true
php artisan storage:link || true

# 6) الترحيلات (آمنة: لا تمسح بيانات)
echo "[rasd] running migrations"
php artisan migrate --force

# 7) كاش الإعدادات للإنتاج (يتجاهل .env بعدها — المفاتيح أصبحت داخل الكاش)
if [ "${APP_ENV:-production}" = "production" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

echo "[rasd] ready — APP_URL=${APP_URL:-(from .env)}"
exec "$@"
