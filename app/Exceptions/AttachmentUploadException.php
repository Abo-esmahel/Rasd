<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Explicit failure for any stage of the attachment pipeline.
 *
 * Invariant enforced by callers:
 *   FILE SENT + FILE NOT SAVED = REQUEST FAILED (never silent success).
 */
class AttachmentUploadException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $stage = 'unknown',
        public readonly ?string $originalName = null,
        public readonly int $filesReceived = 0,
        public readonly int $attachmentsSaved = 0,
        public readonly array $attachmentErrors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function toResponseArray(?int $noteId = null): array
    {
        return [
            'success' => false,
            'note_id' => $noteId,
            'files_received' => $this->filesReceived,
            'attachments_saved' => $this->attachmentsSaved,
            'attachment_errors' => $this->attachmentErrors ?: [
                $this->formatSingleError(),
            ],
        ];
    }

    private function formatSingleError(): array|string
    {
        if ($this->originalName) {
            return [
                'file' => $this->originalName,
                'stage' => $this->stage,
                'message' => $this->getMessage(),
            ];
        }

        return $this->getMessage();
    }
}
