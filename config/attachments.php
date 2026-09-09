<?php
return [
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 2000000),
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 1000000000000000000),
    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 5000),
    'max_per_submission' => (int) env('MAX_ATTACHMENTS_PER_SUBMISSION', 5),
    // حد الصوت بالبايت — يُستخدم فعلياً في NoteService::validateFile
    'max_audio_size' => (int) env('MAX_AUDIO_SIZE', 100 * 1024 * 1024),
];
