<?php

namespace App\Services\Pdf;

use App\Models\Attachment;
use App\Models\GeneralSubmissionAttachment;

/**
 * رابط موقّع للعرض داخل PDF — يفتح المرفق بلا جلسة ولا كوكيز.
 *
 * لماذا: قارئ PDF على الجوال يفتح الرابط بمتصفّح جديد بلا كوكيز جلسة، فيردّ
 * مسار الويب 302 إلى صفحة الدخول، ومسار الـAPI 401 بلا Bearer. أي أن الرابط
 * «قابل للضغط» لكنه لا يُري الملف أبداً — وهو أسوأ من رابط ميت لأنه يبدو
 * صحيحاً.
 *
 * الحل: توقيع HMAC-SHA256 على (المعرّف + نوع + انتهاء) مع سر التطبيق. الرابط
 * يعمل بذاته حتى لو نسخ بين الأجهزة، ويفشل بعد المدة. لا يكشف سراً ولا
 * يمنح صلاحية تصفّح الملاحظات — فقط ملفاً واحداً محدّداً حتى تاريخ محدّد.
 *
 * الأمان مقصود بالحدّ الصريح: لا يمكن إعادة استخدامه على مرفق آخر (المعرّف
 * جزء من التوقيع)، ولا بعد انتهاء (الوقت جزء منه).
 */
final class AttachmentSignedUrl
{
    private const DEFAULT_TTL_MINUTES = 60 * 24 * 7;

    /** @return string|null رابط موقّع، أو null إن تعذّر التوقيع. */
    public static function make(Attachment|GeneralSubmissionAttachment $att, bool $isSubmission = false): ?string
    {
        try {
            $id = (int) $att->getKey();
            if ($id <= 0) {
                return null;
            }

            $ttl = (int) config('pdf.signed_url_ttl_minutes', self::DEFAULT_TTL_MINUTES);
            $expires = time() + max(60, $ttl);

            $payload = $isSubmission ? 's'.$id : 'n'.$id;
            $sig = self::sign($payload.'|'.$expires);

            $base = trim((string) config('pdf.public_base_url', ''));
            $path = $isSubmission
                ? '/submission-attachments/'.$id.'/signed-view'
                : '/attachments/'.$id.'/signed-view';

            $base = $base !== '' ? rtrim($base, '/') : rtrim((string) config('app.url'), '/');

            return $base.'/api'.$path.'?e='.$expires.'&s='.$sig;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * تحقّق من التوقيع — مقارنة ثابتة الزمن لمنع تسريب المعلومات عبر timing.
     */
    public static function verify(int $id, ?string $expires, ?string $signature, bool $isSubmission = false): bool
    {
        if ($signature === null || $expires === null || ! ctype_digit($expires)) {
            return false;
        }
        if ((int) $expires < time()) {
            return false;
        }

        $payload = $isSubmission ? 's'.$id : 'n'.$id;
        $expected = self::sign($payload.'|'.$expires);

        return hash_equals($expected, $signature);
    }

    private static function sign(string $payload): string
    {
        $secret = (string) config('jwt.secret', '');
        if (strlen($secret) < 16) {
            throw new \RuntimeException('secret too short for signed attachment urls');
        }

        return substr(hash_hmac('sha256', $payload, $secret), 0, 32);
    }
}