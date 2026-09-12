<?php

namespace App\Services\Ai;

use App\Models\Note;
use App\Services\AttachmentStorageService;
use Illuminate\Support\Collection;


class AiAttachmentResolver
{
    public function __construct(private AttachmentStorageService $storage)
    {
    }


    public function resolve(Collection $notes, int $maxImages): array
    {
        $images = [];
        $metadata = [];
        $skipped = [];

        foreach ($notes as $note) {
            foreach ($note->attachments as $att) {
                $mime = (string) ($att->mime_type ?? '');
                $kind = str_starts_with($mime, 'image/') ? 'image'
                    : (str_starts_with($mime, 'video/') ? 'video'
                    : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));

                $exists = $this->storage->exists((string) $att->file_path);

                if ($kind === 'image' && $exists && count($images) < $maxImages) {
                    try {
                        $abs = $this->storage->absolutePath((string) $att->file_path);
                        $bytes = @file_get_contents($abs);
                        if ($bytes !== false && strlen($bytes) > 0 && strlen($bytes) < 8 * 1024 * 1024) {
                            $images[] = [
                                'mime' => $mime,
                                'data' => base64_encode($bytes),
                                'attachment_id' => $att->id,
                                'note_id' => $note->id,
                            ];
                            $metadata[] = $this->meta($note, $att, $kind, 'AI_ANALYSIS: AVAILABLE');
                            continue;
                        }
                    } catch (\Throwable $e) {

                    }
                    $skipped[] = ['attachment_id' => $att->id, 'reason' => 'unreadable'];
                    $metadata[] = $this->meta($note, $att, $kind, 'AI_ANALYSIS: NOT_PERFORMED');
                    continue;
                }

                if ($kind === 'image' && $exists && count($images) >= $maxImages) {
                    $reason = $maxImages === 0 ? 'AI_ANALYSIS: NOT_PERFORMED (excluded by user)' : 'AI_ANALYSIS: NOT_PERFORMED (limit)';
                    $metadata[] = $this->meta($note, $att, $kind, $reason);
                    continue;
                }


                $flag = $exists ? 'AI_ANALYSIS: NOT_PERFORMED' : 'AI_ANALYSIS: NOT_PERFORMED (missing)';
                $metadata[] = $this->meta($note, $att, $kind, $flag);
            }
        }

        return ['images' => $images, 'metadata' => $metadata, 'skipped' => $skipped];
    }

    private function meta(Note $note, $att, string $kind, string $flag): array
    {
        return [
            'note_id' => $note->id,
            'attachment_id' => $att->id,
            'kind' => $kind,
            'mime' => (string) ($att->mime_type ?? ''),
            'size' => (int) ($att->file_size ?? 0),
            'original_name' => (string) ($att->original_name ?? ''),
            'flag' => $flag,
        ];
    }
}
