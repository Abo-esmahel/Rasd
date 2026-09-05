<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteRejectedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class NoteService
{
    public function createDraft(User $user, array $data): Note
    {
        if (!$user->isMonitor() && !$user->isReportWriter()) {
            throw new InvalidArgumentException('غير مصرح لك بإنشاء الملاحظات');
        }

        // Strip any client-supplied system fields (mass assignment protection)
        $allowed = array_intersect_key($data, array_flip(['floor_number', 'camera_number', 'observed_at', 'observed_end_at', 'description']));
        $allowed['user_id'] = $user->id;

        // كاتب التقرير: ملاحظاته تُقبل فوراً
        if ($user->isReportWriter()) {
            $allowed['status'] = Note::STATUS_ACCEPTED;
            $allowed['processed_by'] = $user->id;
            $allowed['processed_at'] = now();
        } else {
            $allowed['status'] = Note::STATUS_DRAFT;
        }

        return Note::create($allowed);
    }

    public function updateNote(User $user, Note $note, array $data): Note
    {
        // Only allow updatable fields; prevent mass assignment of system columns
        $allowed = array_intersect_key($data, array_flip(['floor_number', 'camera_number', 'observed_at', 'observed_end_at', 'description']));
        if (empty($allowed)) {
            return $note->fresh();
        }
        $note->update($allowed);
        return $note->fresh();
    }

    public function deleteDraft(User $user, Note $note): void
    {
        if (!$note->isDraft() || $note->user_id !== $user->id) {
            throw new InvalidArgumentException('لا يمكن حذف هذه الملاحظة');
        }

        DB::transaction(function () use ($note) {
            foreach ($note->attachments as $attachment) {
                try {
                    Storage::disk('cloudinary')->delete($attachment->file_path);
                } catch (\Throwable $e) {}
                $attachment->delete();
            }
            $note->delete();
        });
    }

    public function sendNote(User $user, Note $note): Note
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإرسال هذه الملاحظة');
        }

        if (!$note->isDraft()) {
            throw new InvalidArgumentException('يمكن إرسال المسودات فقط');
        }

        // كاتب التقرير مشرف: إرسال المسودة = قبول فوري
        if ($user->isReportWriter()) {
            $note->update([
                'status' => Note::STATUS_ACCEPTED,
                'sent_at' => now(),
                'rejection_reason' => null,
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);
        } else {
            $note->update([
                'status' => Note::STATUS_PENDING,
                'sent_at' => now(),
                'rejection_reason' => null,
                'processed_by' => null,
                'processed_at' => null,
            ]);
        }

        return $note->fresh();
    }

    public function acceptNote(User $user, Note $note): Note
    {
        if (!$user->isReportWriter()) {
            throw new InvalidArgumentException('غير مصرح لك بقبول الملاحظات');
        }

        if (!$note->isPending()) {
            throw new InvalidArgumentException('يمكن قبول الملاحظات قيد المراجعة فقط');
        }

        $note->update([
            'status' => Note::STATUS_ACCEPTED,
            'processed_by' => $user->id,
            'processed_at' => now(),
        ]);

        return $note->fresh();
    }

    public function rejectNote(User $user, Note $note, string $reason): Note
    {
        if (!$user->isReportWriter()) {
            throw new InvalidArgumentException('غير مصرح لك برفض الملاحظات');
        }

        if (!$note->isPending()) {
            throw new InvalidArgumentException('يمكن رفض الملاحظات قيد المراجعة فقط');
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('سبب الرفض مطلوب');
        }

        $note->update([
            'status' => Note::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_by' => $user->id,
            'processed_at' => now(),
        ]);

        $fresh = $note->fresh();
        // إشعار لصاحب الملاحظة — في شريط الإشعارات (DB) + سيظهر كـ Browser Notification
        try {
            $owner = $fresh->owner ?? $fresh->user;
            if ($owner && $owner->id !== $user->id) {
                $owner->notify(new NoteRejectedNotification($fresh, $reason, $user->name));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('فشل إرسال إشعار الرفض: '.$e->getMessage());
        }

        return $fresh;
    }

    public function resendRejectedNote(User $user, Note $note): Note
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإعادة إرسال هذه الملاحظة');
        }

        if (!$note->isRejected()) {
            throw new InvalidArgumentException('يمكن إعادة إرسال الملاحظات المرفوضة فقط');
        }

        $note->update([
            'status' => Note::STATUS_PENDING,
            'sent_at' => now(),
            'rejection_reason' => null,
            'processed_by' => null,
            'processed_at' => null,
        ]);

        return $note->fresh();
    }

    public function addAttachment(User $user, Note $note, UploadedFile $file): Attachment
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإضافة مرفقات لهذه الملاحظة');
        }

        if ($note->isAccepted()) {
            throw new InvalidArgumentException('لا يمكن تعديل مرفقات الملاحظة المقبولة');
        }

        $maxAttachments = (int) config('attachments.max_per_note', 5);
        if ($note->attachments()->count() >= $maxAttachments) {
            throw new InvalidArgumentException("الحد الأقصى للمرفقات هو $maxAttachments");
        }

        $this->validateFile($file);

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/tiff' => 'tiff',
            'image/bmp' => 'bmp',
            'image/avif' => 'avif',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/3gpp' => '3gp',
            'video/3gpp2' => '3g2',
            'video/x-matroska' => 'mkv',
            'video/mpeg' => 'mpg',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'video/ogg' => 'ogv',
            'video/mp2t' => 'ts',
            'video/x-mts' => 'mts',
            'video/mpeg2' => 'm2v',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/opus' => 'opus',
            'audio/webm' => 'webm',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/aacp' => 'aac',
            'audio/flac' => 'flac',
            'audio/x-flac' => 'flac',
            'audio/wma' => 'wma',
            'audio/x-ms-wma' => 'wma',
            'audio/aiff' => 'aiff',
            'audio/x-aiff' => 'aiff',
            'audio/amr' => 'amr',
            'audio/3gpp' => '3ga',
            'audio/midi' => 'mid',
            'audio/x-midi' => 'mid',
            'audio/basic' => 'au',
        ];
        $finfoTmp = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeTmp = finfo_file($finfoTmp, $file->getRealPath());
        finfo_close($finfoTmp);
        $safeExtension = $mimeMap[$realMimeTmp] ?? $extension;
        if (!in_array($safeExtension, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'avi', '3gp', 'mkv', 'mpg', 'm4v', 'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'wma', 'flac', 'opus'])) {
            $safeExtension = $extension === 'jpeg' ? 'jpg' : $extension;
        }
        // Cloudinary: تخزين بدون امتداد لتجنب تكرار الامتداد في الرابط (cat.jpg.jpg)
        // Cloudinary يحدد الصيغة تلقائيًا من resource_type=auto
        // سياسة المشروع: ممنوع أي حفظ محلي — كل المرفقات على Cloudinary فقط
        $safeName = \Illuminate\Support\Str::uuid()->toString();
        $path = 'notes/' . $note->id . '/' . $safeName;
        try {
            $file->storeAs('notes/' . $note->id, $safeName, 'cloudinary');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Cloudinary attachment upload failed: '.$e->getMessage(), ['note_id' => $note->id]);
            throw new InvalidArgumentException('تعذّر رفع الملف إلى التخزين السحابي (Cloudinary). تحقق من الاتصال وحاول مجدداً.');
        }

        return Attachment::create([
            'note_id' => $note->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function removeAttachment(User $user, Attachment $attachment): void
    {
        $note = $attachment->note;

        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بحذف هذا المرفق');
        }

        if ($note->isAccepted()) {
            throw new InvalidArgumentException('لا يمكن حذف مرفقات الملاحظة المقبولة');
        }

        DB::transaction(function () use ($attachment) {
            try {
                Storage::disk('cloudinary')->delete($attachment->file_path);
            } catch (\Throwable $e) {}
            $attachment->delete();
        });
    }

    public function getVisibleNotesQuery(User $user)
    {
        $query = Note::with(['owner', 'attachments']);

        // الجميع يرى مسوداته الخاصة + كل غير المسودات
        $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('status', '!=', Note::STATUS_DRAFT);
        });

        return $query;
    }

    private function validateFile(UploadedFile $file): void
    {
        // جميع أنواع الصور/الفيديو/الصوت — لا نمنع أي صيغة مشروعة حتى المؤقتة
        $allowedMimes = [
            // صور
            'jpg','jpeg','png','webp','heic','heif','tiff','tif','bmp','avif','gif','svg',
            // فيديو — كل الصيغ الشائعة والمؤقتة
            'mp4','webm','mov','avi','3gp','3gpp','mkv','m4v','mpg','mpeg','wmv','flv','ogv','ts','mts','m2ts','vob','asf','m2v','3g2','f4v','m4p',
            // صوت — كل الصيغ
            'mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','ra','weba','aac','ac3','dts','alac','aiff',
        ];
        $allowedMimeTypes = [
            'image/jpeg','image/png','image/webp','image/heic','image/heif','image/tiff','image/bmp','image/avif','image/gif','image/svg+xml',
            'video/mp4','video/webm','video/quicktime','video/x-msvideo','video/3gpp','video/3gpp2','video/x-matroska','video/mpeg','video/x-ms-wmv','video/x-flv','video/ogg','video/mp2t','video/MP2T','video/x-mts','video/mpeg2','video/x-m4v','video/3gpp-tts',
            'audio/mpeg','audio/wav','audio/x-wav','audio/wave','audio/ogg','audio/opus','audio/webm','audio/mp4','audio/x-m4a','audio/aac','audio/aacp','audio/flac','audio/x-flac','audio/wma','audio/x-ms-wma','audio/aiff','audio/x-aiff','audio/amr','audio/3gpp','audio/midi','audio/x-midi','audio/basic','audio/vnd.wave',
        ];
        $dangerousExtensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pht',
            'html', 'htm', 'js', 'exe', 'sh', 'bat', 'cmd', 'cgi', 'shtml',
        ];

        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = strtolower($file->getClientOriginalName());

        // Reject if final extension is dangerous
        if (in_array($extension, $dangerousExtensions)) {
            throw new InvalidArgumentException('نوع الملف غير مسموح به');
        }

        // Reject if original name contains dangerous double extensions like .php. anywhere
        foreach ($dangerousExtensions as $dangerous) {
            if (str_contains($originalName, '.' . $dangerous . '.') || str_ends_with($originalName, '.' . $dangerous)) {
                throw new InvalidArgumentException('نوع الملف غير مسموح به');
            }
        }

        if (!in_array($extension, $allowedMimes)) {
            throw new InvalidArgumentException('امتداد الملف غير مدعوم. الامتدادات المدعومة: ' . implode(', ', $allowedMimes));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        if (!in_array($realMime, $allowedMimeTypes)) {
            throw new InvalidArgumentException('نوع الملف الفعلي غير مدعوم');
        }

        $maxImageSize = (int) config('attachments.max_image_size', 5120) * 1024;
        $maxVideoSize = (int) config('attachments.max_video_size', 30720) * 1024;
        $maxAudioSize = (int) config('attachments.max_audio_size', 100 * 1024 * 1024);

        if (str_starts_with($realMime, 'image/') && $file->getSize() > $maxImageSize) {
            throw new InvalidArgumentException('حجم الصورة يتجاوز الحد الأقصى المسموح (20MB)');
        }

        if (str_starts_with($realMime, 'video/') && $file->getSize() > $maxVideoSize) {
            throw new InvalidArgumentException('حجم الفيديو يتجاوز الحد الأقصى المسموح (100MB)');
        }

        if (str_starts_with($realMime, 'audio/') && $file->getSize() > $maxAudioSize) {
            throw new InvalidArgumentException('حجم الصوت يتجاوز الحد الأقصى المسموح (100MB)');
        }
    }
}
