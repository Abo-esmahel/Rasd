#!/usr/bin/env bash
# تشغيل سيرفر الرصد بحدود الرفع الخاصة بالمشروع (مستقلة عن إعدادات الجهاز).
#
# لماذا هذا الملف: حدود الرفع في PHP (upload_max_filesize) لا يمكن رفعها
# من داخل Laravel لأنها تُقرأ قبل تشغيل التطبيق، لذا نستخدم php.ini خاصاً
# بالمشروع ونمرّره عبر PHPRC. لا يحتاج أي صلاحيات نظام ولا تعديل حاسوب.
#
# الاستخدام:  ./serve.sh
# الإعدادات:  config/attachments.php و .env (MAX_ATTACHMENT_FILE_BYTES ...)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
HOST="${SERVER_HOST:-0.0.0.0}"
PORT="${SERVER_PORT:-8000}"
INI="${ROOT}/php.ini"

cd "${ROOT}"

if [ ! -f "${INI}" ]; then
  echo "خطأ: ملف الإعدادات غير موجود: ${INI}" >&2
  exit 1
fi

# PHPRC يجعل PHP يقرأ php.ini الخاص بالمشروع بدل php.ini النظامي.
export PHPRC="${INI}"

# php -S أحادي الطلب: طلب واحد يوقف السيرفر كله. توليد PDF شغّل Chrome
# حتى 90 ثانية، فيتجمّد التطبيق كله أثناء الطباعة. PHP_CLI_SERVER_WORKERS
# يشغّل عدة عمّال على نفس المنفذ (ويندوز/الأنظمة بلا pcntl يتجاهلها).
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"

# تحقّق مبكر واضح إن لم يُطبَّق الإعداد.
php -r 'exit(ini_get("upload_max_filesize") === "2M" ? 1 : 0);' || {
  echo "تحذير: لم تُطبَّق حدود php.ini (upload_max_filesize = $(php -r 'echo ini_get("upload_max_filesize");'))" >&2
}

# جذر المستندات هو public/ (يفتّحه server.php عبر ./index.php).
cd "${ROOT}/public"

# عامل الطابور: توليد PDF (وغيره) يجري هنا لا داخل طلب HTTP.
# بدونه تبقى الطابور بلا عامل فلا يُولَّد أي PDF ويبقى العميل ينتظر.
QUEUE_WORKER_PID=""
cleanup() {
  if [ -n "${QUEUE_WORKER_PID}" ] && kill -0 "${QUEUE_WORKER_PID}" 2>/dev/null; then
    kill "${QUEUE_WORKER_PID}" 2>/dev/null || true
    wait "${QUEUE_WORKER_PID}" 2>/dev/null || true
  fi
}
trap cleanup EXIT INT TERM

php -c "${INI}" "${ROOT}/artisan" queue:work \
  --sleep=2 --tries=1 --timeout=180 --max-jobs=500 \
  >"${ROOT}/storage/logs/queue-worker.log" 2>&1 &
QUEUE_WORKER_PID=$!
echo "  عامل الطابور: pid=${QUEUE_WORKER_PID} (سجل: storage/logs/queue-worker.log)"

echo "▶ رصد — http://${HOST}:${PORT}  (حدود الملف: $(php -c "${INI}" -r 'echo ini_get("upload_max_filesize");'))"

php -c "${INI}" -S "${HOST}:${PORT}" \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php