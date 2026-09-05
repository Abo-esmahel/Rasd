<?php

return [
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 5120),
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 30720),
    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 50),
];
