<?php
return [
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 2000000),
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 1000000000000000000),
    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 5000),
    'max_audio_size' => 10 * 1024,
];
