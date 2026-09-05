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
                Storage::disk('private')->delete($attachment->file_path);
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
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/3gpp' => '3gp',
            'video/x-matroska' => 'mkv',
            'video/mpeg' => 'mpg',
        ];
        $finfoTmp = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeTmp = finfo_file($finfoTmp, $file->getRealPath());
        finfo_close($finfoTmp);
        $safeExtension = $mimeMap[$realMimeTmp] ?? $extension;
        if (!in_array($safeExtension, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'avi', '3gp', 'mkv', 'mpg', 'm4v', 'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac'])) {
            $safeExtension = $extension === 'jpeg' ? 'jpg' : $extension;
        }
        $safeName = \Illuminate\Support\Str::uuid()->toString() . '.' . $safeExtension;
        $path = 'notes/' . $note->id . '/' . $safeName;
        $file->storeAs('notes/' . $note->id, $safeName, 'private');

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
            Storage::disk('private')->delete($attachment->file_path);
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
        $allowedMimes = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'avi', '3gp', 'mkv', 'm4v', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'wma', 'flac', 'opus'];
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/x-msvideo',
            'video/3gpp',
            'video/x-matroska',
            'video/mpeg',
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/aac',
            'audio/x-wav',
            'audio/flac',
            'audio/opus',
            'audio/webm',
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
        $maxAudioSize = 10 * 1024 * 1024; // 10MB

        if (str_starts_with($realMime, 'image/') && $file->getSize() > $maxImageSize) {
            throw new InvalidArgumentException('حجم الصورة يتجاوز الحد الأقصى المسموح');
        }

        if (str_starts_with($realMime, 'video/') && $file->getSize() > $maxVideoSize) {
            throw new InvalidArgumentException('حجم الفيديو يتجاوز الحد الأقصى المسموح');
        }

        if (str_starts_with($realMime, 'audio/') && $file->getSize() > $maxAudioSize) {
            throw new InvalidArgumentException('حجم الصوت يتجاوز الحد الأقصى المسموح (10MB)');
        }
    }
}
