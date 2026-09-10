<?php

namespace App\Services;

use App\Exceptions\AttachmentUploadException;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentStorageService
{
    public const DISK = 'attachments';

    public function disk()
    {
        return Storage::disk(self::DISK);
    }

    
    public function store(UploadedFile $file, int $noteId): string
    {
        return $this->storeIn($file, "notes/{$noteId}", ['note_id' => $noteId]);
    }

    
    public function storeSubmission(UploadedFile $file, int $submissionId): string
    {
        return $this->storeIn($file, "submissions/{$submissionId}", ['submission_id' => $submissionId]);
    }

    
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

        
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) ($file->guessExtension() ?: 'bin'));
        }

        $storedName = (string) Str::uuid().'.'.$extension;
        $relativePath = "{$directory}/{$storedName}";

        
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

    public function storageType(Attachment $attachment): string
    {
        return $this->isLocal($attachment) ? 'local' : 'missing';
    }

    public function viewUrl(Attachment $attachment): string
    {
        return route('notes.attachments.view', $attachment);
    }

    
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

        
        if ($mime === 'application/octet-stream' && function_exists('mime_content_type')) {
            try {
                $detected = @mime_content_type($absolute);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            } catch (\Throwable $e) {
            }
        }

        
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
