<?php
return [
    
    
    
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 20480), 
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 102400), 
    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 10),
    'max_per_submission' => (int) env('MAX_ATTACHMENTS_PER_SUBMISSION', 5),
    
    'max_audio_size' => (int) env('MAX_AUDIO_SIZE', 100 * 1024 * 1024),
];
