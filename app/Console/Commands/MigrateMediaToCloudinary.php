<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ترحيل لمرة واحدة للملفات المحلية القديمة (public/private) إلى Cloudinary.
 *
 * - الأفاتار: avatars/{uuid} بدون امتداد
 * - المرفقات: notes/{note_id}/{uuid} بدون امتداد
 * - يُحدّث avatar_path و file_path في DB لتشير للمسار الجديد.
 * - الملفات المحلية تُترك كما هي إلا مع --delete-local.
 *
 * التشغيل على سيرفر الإنتاج بعد النشر:
 *   php artisan media:migrate-to-cloudinary --dry-run   (معاينة فقط)
 *   php artisan media:migrate-to-cloudinary             (تنفيذ)
 *   php artisan media:migrate-to-cloudinary --delete-local (تنفيذ + حذف المحلي بعد نجاح الرفع)
 */
class MigrateMediaToCloudinary extends Command
{
    protected $signature = 'media:migrate-to-cloudinary
                            {--dry-run : عرض ما سيتم ترحيله دون تنفيذ}
                            {--delete-local : حذف الملف المحلي بعد نجاح رفعه}';

    protected $description = 'ترحيل الأفاتار والمرفقات المحلية القديمة إلى Cloudinary (لمرة واحدة)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleteLocal = (bool) $this->option('delete-local');

        $stats = ['avatars' => 0, 'attachments' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0];

        // — 1) الأفاتار —
        $this->info('فحص الأفاتار...');
        User::whereNotNull('avatar_path')->chunkById(100, function ($users) use (&$stats, $dryRun, $deleteLocal) {
            foreach ($users as $user) {
                $old = $user->avatar_path;

                // الجديد بدون امتداد = تم ترحيله مسبقًا
                if (! pathinfo($old, PATHINFO_EXTENSION)) {
                    $stats['skipped']++;
                    continue;
                }

                if (! Storage::disk('public')->exists($old)) {
                    $stats['missing']++;
                    $this->warn("مفقود محليًا: user #{$user->id} → {$old}");
                    continue;
                }

                $new = 'avatars/'.Str::uuid()->toString();
                if ($dryRun) {
                    $stats['avatars']++;
                    $this->line("سيُرحّل: {$old} → {$new}");
                    continue;
                }

                try {
                    Storage::disk('cloudinary')->put($new, Storage::disk('public')->get($old));
                    $user->update(['avatar_path' => $new]);
                    if ($deleteLocal) {
                        Storage::disk('public')->delete($old);
                    }
                    $stats['avatars']++;
                    $this->line("رُحّل: user #{$user->id}");
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("فشل user #{$user->id}: ".$e->getMessage());
                }
            }
        });

        // — 2) المرفقات —
        $this->info('فحص المرفقات...');
        Attachment::with('note:id')->chunkById(100, function ($attachments) use (&$stats, $dryRun, $deleteLocal) {
            foreach ($attachments as $attachment) {
                $old = $attachment->file_path;

                if (! pathinfo($old, PATHINFO_EXTENSION)) {
                    $stats['skipped']++;
                    continue;
                }

                if (! Storage::disk('private')->exists($old)) {
                    $stats['missing']++;
                    $this->warn("مفقود محليًا: attachment #{$attachment->id} → {$old}");
                    continue;
                }

                $new = 'notes/'.$attachment->note_id.'/'.Str::uuid()->toString();
                if ($dryRun) {
                    $stats['attachments']++;
                    $this->line("سيُرحّل: {$old} → {$new}");
                    continue;
                }

                try {
                    Storage::disk('cloudinary')->put($new, Storage::disk('private')->get($old));
                    $attachment->update(['file_path' => $new]);
                    if ($deleteLocal) {
                        Storage::disk('private')->delete($old);
                    }
                    $stats['attachments']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("فشل attachment #{$attachment->id}: ".$e->getMessage());
                }
            }
        });

        $this->table(
            ['البند', 'العدد'],
            [
                ['أفاتار مُرحّل', $stats['avatars']],
                ['مرفقات مُرحّلة', $stats['attachments']],
                ['متخطّى (جديد مسبقًا)', $stats['skipped']],
                ['مفقود محليًا', $stats['missing']],
                ['فشل', $stats['failed']],
            ]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
