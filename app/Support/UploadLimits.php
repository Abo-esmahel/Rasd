<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;

/**
 * حدود الرفع — إعدادات مستقلة للمشروع، لا تعتمد على php.ini الخاص بالجهاز.
 *
 * لماذا: PHP يقرأ upload_max_filesize من php.ini قبل تشغيل التطبيق،
 * فلا يستطيع Laravel رفعها أثناء المعالجة. الحل: نضبطها من bootstrap
 * عبر ini_set ليعمل الحد نفسه مع أي طريقة تشغيل (artisan serve أو php -S).
 *
 * القيم تأتي من config/attachments.php (أي من .env) فهي قابلة للتعديل
 * من إعدادات المشروع ولا تحتاج صلاحيات نظام ولا تعديل حاسوب.
 */
final class UploadLimits
{
    /**
     * حدود PHP المطبَّقة الآن على العملية الحالية (بايت).
     *
     * تنبيه مهم: upload_max_filesize و post_max_size من نوع PHP_INI_PERDIR
     * — لا يمكن تغييرهما بـ ini_set أثناء التشغيل (يُرجع false). لذلك تُضبط
     * عبر php.ini الخاص بالمشروع (يُحمَّل بـ PHPRC عند التشغيل بـ ./serve.sh).
     * هذه الدالة للقراءة والتشخيص فقط، أما الضبط فيتم في php.ini.
     */
    public static function apply(?Application $app = null): void
    {
        unset($app);
    }

    /** الحد الفعّال الآن كما تطبّقه PHP — لعرضه في أخطاء الرفع. */
    public static function effectivePerFile(): int
    {
        $v = self::toBytes((string) ini_get('upload_max_filesize'));

        return $v === -1 ? PHP_INT_MAX : $v;
    }

    /** الحد الذي نريده من إعدادات المشروع (قد يختلف عن الفعّال). */
    public static function desiredPerFile(?Application $app = null): int
    {
        return (int) ($app?->make('config')->get('attachments.max_file_bytes')
            ?: (512 * 1024 * 1024));
    }

    /** الحد الذي يسمح به PHP الآن — يُستخدم في التحقق قبل الرفع. */
    public static function enforcePerFile(int $declaredBytes, ?Application $app = null): int
    {
        $desired = self::desiredPerFile($app);
        $effective = self::effectivePerFile();

        return $desired > 0 ? min($desired, $effective) : $effective;
    }

    /** يحوّل 2M/512K/G Unlimited إلى بايت، أو -1 لغير المحدود. */
    public static function toBytes(string $value): int
    {
        $v = trim($value);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $unit = strtoupper(substr($v, -1));
        $num = (int) $v;
        return match ($unit) {
            'G' => $num * 1024 * 1024 * 1024,
            'M' => $num * 1024 * 1024,
            'K' => $num * 1024,
            default => $num,
        };
    }

    public static function human(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024 * 1024), 1), '0'), '.').'G';
        }
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.').'M';
        }
        if ($bytes >= 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.').'K';
        }

        return (string) $bytes;
    }
}