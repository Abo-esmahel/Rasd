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

        // 2. Safe unique name — never use original name for storage.
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) ($file->guessExtension() ?: 'bin'));
        }

        $storedName = (string) Str::uuid().'.'.$extension;
        $relativePath = "{$directory}/{$storedName}";

        // 2.5 ضغط الصور محلياً قبل التخزين لتسريع الرفع وتوفير المساحة (لو GD متاح)
        $fileToStore = $file;
        $tempCompressedPath = null;
        if (str_starts_with((string) $file->getMimeType(), 'image/') && $extension !== 'svg' && $extension !== 'gif') {
            $optimized = $this->tryOptimizeImage($file);
            if ($optimized) {
                $fileToStore = $optimized['file'];
                $tempCompressedPath = $optimized['tempPath'];
                $storedName = (string) Str::uuid().'.'.$optimized['ext'];
                $relativePath = "{$directory}/{$storedName}";
            }
        }

        // 3. Write via Laravel Storage API — استخدم stream للسرعة
        try {
            // putFileAs يستخدم move بشكل محسن، لكن نستخدم writeStream للملفات المضغوطة
            if ($tempCompressedPath) {
                $stream = fopen($fileToStore->getRealPath(), 'r');
                $this->disk()->writeStream($relativePath, $stream);
                if (is_resource($stream)) fclose($stream);
                $stored = $relativePath;
            } else {
                $stored = $this->disk()->putFileAs($directory, $fileToStore, $storedName);
            }
        } catch (\Throwable $e) {
            if ($tempCompressedPath && file_exists($tempCompressedPath)) @unlink($tempCompressedPath);
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

        if ($tempCompressedPath && file_exists($tempCompressedPath)) @unlink($tempCompressedPath);

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

        // 4. Physical verification before returning — file MUST exist.
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

    /**
     * ضغط الصورة محلياً باستخدام GD (لو متاح) — يقلل الحجم 60-80% بدون فقدان ملحوظ
     * يعيد UploadedFile جديد مضغوط أو null لو فشل
     */
    private function tryOptimizeImage(UploadedFile $file): ?array
    {
        if (!extension_loaded('gd')) return null;
        $realPath = $file->getRealPath();
        if (!$realPath || !file_exists($realPath)) return null;
        $size = $file->getSize() ?: 0;
        // لا تضغط الصور الصغيرة جداً (<500KB) — لا فائدة
        if ($size < 500 * 1024) return null;
        try {
            $info = @getimagesize($realPath);
            if (!$info) return null;
            [$width, $height, $type] = $info;
            // لو الصورة أصغر من 1920px لا داعي لتغيير الأبعاد، فقط إعادة ضغط
            $maxDim = 1920;
            $needResize = $width > $maxDim || $height > $maxDim;
            if (!$needResize && $size < 2 * 1024 * 1024) return null; // <2MB وصغيرة الأبعاد = اتركها

            $src = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
                IMAGETYPE_PNG => @imagecreatefrompng($realPath),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : null,
                IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($realPath) : null,
                default => null,
            };
            if (!$src) return null;

            if ($needResize) {
                $ratio = min($maxDim / $width, $maxDim / $height);
                $newW = (int) round($width * $ratio);
                $newH = (int) round($height * $ratio);
                $dst = imagecreatetruecolor($newW, $newH);
                // حافظ على الشفافية لـ PNG/WebP
                if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                    imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($src);
                $src = $dst;
            }

            $tmpPath = sys_get_temp_dir() . '/' . Str::uuid() . '.jpg';
            // احفظ كـ JPEG بجودة 82 — أفضل توازن حجم/جودة، أو WebP لو الأصل WebP
            $ok = false;
            $ext = 'jpg';
            if ($type === IMAGETYPE_PNG && function_exists('imagepng')) {
                // حاول WebP لو متاح (أصغر)
                if (function_exists('imagewebp')) {
                    $tmpPath = sys_get_temp_dir() . '/' . Str::uuid() . '.webp';
                    $ok = @imagewebp($src, $tmpPath, 80);
                    $ext = 'webp';
                    if (!$ok) {
                        $tmpPath = sys_get_temp_dir() . '/' . Str::uuid() . '.jpg';
                        $ok = @imagejpeg($src, $tmpPath, 82);
                        $ext = 'jpg';
                    }
                } else {
                    $ok = @imagejpeg($src, $tmpPath, 82);
                }
            } elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) {
                $tmpPath = sys_get_temp_dir() . '/' . Str::uuid() . '.webp';
                $ok = @imagewebp($src, $tmpPath, 80);
                $ext = 'webp';
            } else {
                $ok = @imagejpeg($src, $tmpPath, 82);
            }
            imagedestroy($src);
            if (!$ok || !file_exists($tmpPath) || filesize($tmpPath) >= $size) {
                if (file_exists($tmpPath)) @unlink($tmpPath);
                return null;
            }
            $newFile = new UploadedFile($tmpPath, $file->getClientOriginalName(), $ext === 'webp' ? 'image/webp' : 'image/jpeg', null, true);
            return ['file' => $newFile, 'tempPath' => $tmpPath, 'ext' => $ext];
        } catch (\Throwable $e) {
            return null;
        }
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
