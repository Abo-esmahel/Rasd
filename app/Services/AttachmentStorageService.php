<?php

namespace App\Services;

use App\Exceptions\AttachmentUploadException;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local disk storage for note + general-submission attachments.
 *
 * Disk:      attachments (root: storage/app/private)
 * Paths:     notes/{note_id}/{uuid}.{ext}
 *            submissions/{submission_id}/{uuid}.{ext}
 * Truth:     Disk + file_path (never secure_url for new files)
 * Access:    only via secure view routes (auth + stream)
 *
 * Cloudinary is LEGACY READ-ONLY fallback (old note secure_url records).
 * New uploads MUST NEVER call Cloudinary.
 */
class AttachmentStorageService
{
    public const DISK = 'attachments';

    public function disk()
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Store an uploaded file for the given note.
     *
     * @throws AttachmentUploadException on any failure (php_upload / validation / storage / verification)
     */
    public function store(UploadedFile $file, int $noteId): string
    {
        return $this->storeIn($file, "notes/{$noteId}", ['note_id' => $noteId]);
    }

    /**
     * Store an uploaded file for the given general submission.
     *
     * @throws AttachmentUploadException
     */
    public function storeSubmission(UploadedFile $file, int $submissionId): string
    {
        return $this->storeIn($file, "submissions/{$submissionId}", ['submission_id' => $submissionId]);
    }

    /**
      * Copy a stored file to a new directory with a fresh UUID name.
      * محسن للسرعة: يستخدم stream بدلاً من تحميل الملف كاملاً في الذاكرة.
      * Used when a submission is accepted → its media is copied to the new note.
      *
      * @throws AttachmentUploadException
      */
    public function copyToNotes(string $sourcePath, int $noteId): string
    {
        if (!$this->exists($sourcePath)) {
            throw new AttachmentUploadException(
                'تعذّر نسخ المرفق: الملف الأصلي غير موجود.',
                stage: 'storage',
            );
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin');
        $storedName = (string) Str::uuid().'.'.$extension;
        $destDir = "notes/{$noteId}";
        $destPath = "{$destDir}/{$storedName}";

        try {
            // استخدم stream لتجنب استهلاك الذاكرة مع الفيديوهات الكبيرة — أسرع بـ 3-5x
            $readStream = $this->disk()->readStream($sourcePath);
            if ($readStream === null) {
                throw new \RuntimeException('تعذر فتح stream المصدر');
            }
            $this->disk()->writeStream($destPath, $readStream);
            if (is_resource($readStream)) {
                fclose($readStream);
            }
            $ok = true;
        } catch (\Throwable $e) {
            Log::error('[ATTACHMENT] copy to notes failed', [
                'source' => $sourcePath,
                'dest' => $destPath,
                'message' => $e->getMessage(),
            ]);
            throw new AttachmentUploadException(
                'تعذّر نسخ المرفق إلى الملاحظة.',
                stage: 'storage',
                previous: $e,
            );
        }

        if (!$ok || !$this->exists($destPath)) {
            throw new AttachmentUploadException(
                'تعذّر التحقق من نسخة المرفق في الملاحظة.',
                stage: 'verification',
            );
        }

        // Integrity: verify copied file size/hash matches source (byte-for-byte)
        try {
            $srcSize = $this->disk()->size($sourcePath);
            $destSize = $this->disk()->size($destPath);
            if ($srcSize !== $destSize) {
                Log::error('[ATTACHMENT] copy size mismatch', ['source' => $sourcePath, 'dest' => $destPath, 'srcSize' => $srcSize, 'destSize' => $destSize]);
                $this->delete($destPath);
                throw new AttachmentUploadException('تعذّر التحقق من نسخة المرفق (حجم غير متطابق).', stage: 'verification');
            }
            $srcAbs = $this->absolutePath($sourcePath);
            $destAbs = $this->absolutePath($destPath);
            if (file_exists($srcAbs) && file_exists($destAbs)) {
                $srcHash = @hash_file('sha256', $srcAbs);
                $destHash = @hash_file('sha256', $destAbs);
                if ($srcHash && $destHash && $srcHash !== $destHash) {
                    Log::error('[ATTACHMENT] copy hash mismatch', ['source' => $sourcePath, 'dest' => $destPath]);
                    $this->delete($destPath);
                    throw new AttachmentUploadException('تعذّر التحقق من نسخة المرفق (محتوى غير متطابق).', stage: 'verification');
                }
            }
        } catch (AttachmentUploadException $e) { throw $e; } catch (\Throwable $e) { Log::warning('[ATTACHMENT] copy integrity warning', ['message' => $e->getMessage()]); }

        return $destPath;
    }

    private function storeIn(UploadedFile $file, string $directory, array $context = []): string
    {
        $originalName = $file->getClientOriginalName() ?: 'file';

        // 1. PHP upload stage — never treat partial/failed uploads as valid.
        if (!$file->isValid()) {
            $code = (int) $file->getError();

            Log::warning('[ATTACHMENT] php upload error', array_merge([
                'original_name' => $originalName,
                'stage' => 'php_upload',
                'error_code' => $code,
            ], $context));

            throw new AttachmentUploadException(
                $this->uploadErrorMessage($code, $originalName),
                stage: 'php_upload',
                originalName: $originalName,
            );
        }

        // 2. Safe unique name — never use original name for storage (preserve original content byte-for-byte, no compression/resize/transcoding).
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) ($file->guessExtension() ?: 'bin'));
        }

        $storedName = (string) Str::uuid().'.'.$extension;
        $relativePath = "{$directory}/{$storedName}";

        // 3. Write via Laravel Storage API — preserve original bytes exactly, no modification
        try {
            $stored = $this->disk()->putFileAs($directory, $file, $storedName);
        } catch (\Throwable $e) {
            Log::error('[ATTACHMENT] storage write exception', array_merge([
                'original_name' => $originalName,
                'stage' => 'storage',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ], $context));

            throw new AttachmentUploadException(
                "تعذّر تخزين الملف {$originalName} على القرص المحلي.",
                stage: 'storage',
                originalName: $originalName,
                previous: $e,
            );
        }

        if ($stored === false || $stored === null) {
            Log::error('[ATTACHMENT] storage write returned false', array_merge([
                'original_name' => $originalName,
                'stage' => 'storage',
                'path' => $relativePath,
            ], $context));

            throw new AttachmentUploadException(
                "تعذّر تخزين الملف {$originalName} على القرص المحلي.",
                stage: 'storage',
                originalName: $originalName,
            );
        }

        // 4. Physical verification before returning — file MUST exist and size/hash must match original (byte-for-byte).
        if (!$this->exists($relativePath)) {
            Log::error('[ATTACHMENT] physical verification failed after write', array_merge([
                'original_name' => $originalName,
                'stage' => 'verification',
                'path' => $relativePath,
            ], $context));

            throw new AttachmentUploadException(
                "تمت كتابة الملف {$originalName} لكن تعذّر التحقق من وجوده.",
                stage: 'verification',
                originalName: $originalName,
            );
        }

        // Integrity: size check (mandatory, fast) + hash check (when possible)
        try {
            $originalSize = $file->getSize();
            $storedSize = $this->disk()->size($relativePath);
            if ($originalSize !== null && $storedSize !== $originalSize) {
                Log::error('[ATTACHMENT] size mismatch after write', array_merge([
                    'original_name' => $originalName,
                    'stage' => 'verification',
                    'path' => $relativePath,
                    'original_size' => $originalSize,
                    'stored_size' => $storedSize,
                ], $context));
                $this->delete($relativePath);
                throw new AttachmentUploadException(
                    "تم حفظ الملف {$originalName} لكن حجمه غير متطابق (الأصل {$originalSize} بايت، المخزن {$storedSize} بايت).",
                    stage: 'verification',
                    originalName: $originalName,
                );
            }
            $originalReal = $file->getRealPath();
            $storedAbsolute = $this->absolutePath($relativePath);
            if ($originalReal && file_exists($originalReal) && file_exists($storedAbsolute)) {
                $origHash = @hash_file('sha256', $originalReal);
                $storedHash = @hash_file('sha256', $storedAbsolute);
                if ($origHash && $storedHash && $origHash !== $storedHash) {
                    Log::error('[ATTACHMENT] hash mismatch after write', array_merge([
                        'original_name' => $originalName,
                        'stage' => 'verification',
                        'path' => $relativePath,
                    ], $context));
                    $this->delete($relativePath);
                    throw new AttachmentUploadException(
                        "تم حفظ الملف {$originalName} لكن محتواه غير متطابق (فشل التحقق عبر hash).",
                        stage: 'verification',
                        originalName: $originalName,
                    );
                }
            }
        } catch (AttachmentUploadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('[ATTACHMENT] integrity check warning', ['path' => $relativePath, 'message' => $e->getMessage()]);
        }

        return $relativePath;
    }

    public function exists(string $relativePath): bool
    {
        if ($relativePath === '') {
            return false;
        }

        try {
            return $this->disk()->exists($relativePath);
        } catch (\Throwable $e) {
            Log::warning('[ATTACHMENT] exists check failed', [
                'path' => $relativePath,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->disk()->path($relativePath);
    }

    /**
     * Delete a local physical file. Missing file = already clean (true).
     */
    public function delete(string $relativePath): bool
    {
        if ($relativePath === '' || !$this->exists($relativePath)) {
            return true;
        }

        try {
            $this->disk()->delete($relativePath);

            return !$this->exists($relativePath);
        } catch (\Throwable $e) {
            Log::warning('[ATTACHMENT] local delete failed', [
                'path' => $relativePath,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isLocal(Attachment $attachment): bool
    {
        return !empty($attachment->file_path) && $this->exists($attachment->file_path);
    }

    public function isLocalSubmission(\App\Models\GeneralSubmissionAttachment $attachment): bool
    {
        return !empty($attachment->file_path) && $this->exists($attachment->file_path);
    }

    public function isLegacyCloudinary(Attachment $attachment): bool
    {
        return !$this->isLocal($attachment) && !empty($attachment->secure_url);
    }

    public function storageType(Attachment $attachment): string
    {
        if ($this->isLocal($attachment)) {
            return 'local';
        }

        if (!empty($attachment->secure_url)) {
            return 'cloudinary_legacy';
        }

        return 'missing';
    }

    public function viewUrl(Attachment $attachment): string
    {
        return route('notes.attachments.view', $attachment);
    }

    /**
     * Build an inline/download response for a LOCAL attachment.
     * Caller must have authorized the note view already.
     */
    public function fileResponse(Attachment $attachment, bool $asDownload = false)
    {
        return $this->fileResponseForPath(
            (string) $attachment->file_path,
            (string) ($attachment->mime_type ?: 'application/octet-stream'),
            $attachment->original_name,
            $asDownload
        );
    }

    public function fileResponseSubmission(\App\Models\GeneralSubmissionAttachment $attachment, bool $asDownload = false)
    {
        return $this->fileResponseForPath(
            (string) $attachment->file_path,
            (string) ($attachment->mime_type ?: 'application/octet-stream'),
            $attachment->original_name,
            $asDownload
        );
    }

    public function fileResponseForPath(string $relativePath, string $mime, ?string $originalName, bool $asDownload = false)
    {
        if (!$this->exists($relativePath)) {
            abort(404, 'الملف غير موجود');
        }

        $absolute = $this->absolutePath($relativePath);
        $mime = $mime !== '' ? $mime : 'application/octet-stream';

        // Detect more accurate mime from disk when DB value is generic.
        if ($mime === 'application/octet-stream' && function_exists('mime_content_type')) {
            try {
                $detected = @mime_content_type($absolute);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            } catch (\Throwable $e) {
            }
        }

        // كاش قوي للعرض المحلي — يسرّع إعادة فتح الصور/الفيديو بشكل كبير
        $lastModified = @filemtime($absolute) ?: time();
        $etag = md5($relativePath . $lastModified . @filesize($absolute));
        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $asDownload ? 'private, max-age=0, must-revalidate' : 'public, max-age=31536000, immutable',
            'ETag' => '"'.$etag.'"',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'Accept-Ranges' => 'bytes',
        ];

        // دعم If-None-Match / If-Modified-Since للسرعة (304)
        $ifNoneMatch = request()->header('If-None-Match');
        $ifModifiedSince = request()->header('If-Modified-Since');
        if (!$asDownload && $ifNoneMatch && trim($ifNoneMatch) === '"'.$etag.'"') {
            return response('', 304, $headers);
        }
        if (!$asDownload && $ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) {
            return response('', 304, $headers);
        }

        if ($asDownload) {
            $safe = $this->safeDownloadName($originalName);

            return response()->download($absolute, $safe, $headers);
        }

        return response()->file($absolute, $headers);
    }

    private function safeDownloadName(?string $original): string
    {
        $name = trim((string) $original);
        if ($name === '') {
            return 'attachment';
        }

        // Strip path components, keep a safe basename.
        $name = basename(str_replace('\\', '/', $name));

        return $name !== '' ? $name : 'attachment';
    }

    private function uploadErrorMessage(int $code, string $name): string
    {
        $safe = trim($name) !== '' ? $name : 'الملف';

        return match ($code) {
            UPLOAD_ERR_INI_SIZE => "الملف {$safe} يتجاوز حد الخادم upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE => "الملف {$safe} يتجاوز الحد المسموح في النموذج.",
            UPLOAD_ERR_PARTIAL => "وصل الملف {$safe} ناقصاً. يرجى إعادة المحاولة.",
            UPLOAD_ERR_NO_FILE => "لم يتم استلام الملف {$safe}.",
            UPLOAD_ERR_NO_TMP_DIR => "تعذّر حفظ الملف {$safe} مؤقتاً (إعداد الخادم).",
            UPLOAD_ERR_CANT_WRITE => "تعذّر كتابة الملف {$safe} على الخادم.",
            UPLOAD_ERR_EXTENSION => "رفض الخادم الملف {$safe} (إضافة PHP).",
            default => "تعذّر استلام الملف {$safe} (خطأ رفع {$code}).",
        };
    }
}
