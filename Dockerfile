# RASD — صورة واحدة متنقلة (PHP 8.4 + Apache + SQLite + Vite build)
# البناء: docker build -t rasd .
# التشغيل: docker compose up -d

# ---------- Stage 1: بناء واجهة Vite ----------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/
RUN npm run build

# ---------- Stage 2: PHP + Apache ----------
FROM php:8.4-apache

# مكتبات النظام + إضافات PHP المطلوبة (composer.json: gd, zip, pdo_sqlite, bcmath...)
# chromium: طباعة PDF عبر /api/print (headless --no-sandbox جاهز في الكود)
# ffmpeg: مصغّرات الفيديو — بدونه شارة نصية تلقائياً
# خطوط Noto/Amiri: ترتيب عربي سليم في مطبوعات Chrome
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip sqlite3 \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev libsqlite3-dev \
    chromium ffmpeg \
    fonts-liberation fonts-dejavu-core fonts-noto-core fonts-amiri \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite bcmath gd zip pcntl opcache exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# تلميح صريح — الكود يكتشفها تلقائياً أيضاً عبر LocalBinary (PATH + مسارات شائعة)
ENV CHROME_BIN=/usr/bin/chromium
ENV FFMPEG_BIN=/usr/bin/ffmpeg

# Composer من صورته الرسمية
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache يشير إلى public/ ويدعم Authorization header (JWT)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf 2>/dev/null || true \
    && printf '<Directory /var/www/html/public>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/rasd.conf \
    && a2enconf rasd

# حدود الرفع (تطابق .env.example + docs/DEVELOPMENT.md)
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# اعتمادات PHP أولاً للاستفادة من كاش الطبقات
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# كامل الكود (ما عدا المستثنى في .dockerignore)
COPY . .
# أصول Vite الجاهزة من مرحلة frontend
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true \
    && mkdir -p database storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/rasd-entrypoint.sh
RUN chmod +x /usr/local/bin/rasd-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["rasd-entrypoint.sh"]
CMD ["apache2-foreground"]
