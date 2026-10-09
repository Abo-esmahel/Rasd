<?php
return [
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 40960),
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 512000),
    'max_audio_size' => (int) env('MAX_AUDIO_SIZE', 512 * 1024 * 1024),

    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 10),
    'max_per_submission' => (int) env('MAX_ATTACHMENTS_PER_SUBMISSION', 5),

    'max_file_bytes' => (int) env('MAX_ATTACHMENT_FILE_BYTES', 512 * 1024 * 1024),
    'max_request_bytes' => (int) env('MAX_ATTACHMENT_REQUEST_BYTES', 1024 * 1024 * 1024),
];
