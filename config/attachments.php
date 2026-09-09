<?php
return [
    // القيم بالكيلوبايت (KB) — متوافقة مع validation max:xxx في Laravel
    // للرفع المحلي السريع: 20MB صور، 100MB فيديو، 100MB صوت — ضمن حد PHP 128M
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 20480), // KB
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 102400), // KB (100MB)
    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 10),
    'max_per_submission' => (int) env('MAX_ATTACHMENTS_PER_SUBMISSION', 5),
    // حد الصوت بالبايت — يُستخدم فعلياً في NoteService::validateFile
    'max_audio_size' => (int) env('MAX_AUDIO_SIZE', 100 * 1024 * 1024),
    // ضغط الصور التلقائي (GD) — يقلل 60-80% من الحجم محلياً
    'image_max_dimension' => (int) env('IMAGE_MAX_DIMENSION', 1920),
    'image_jpeg_quality' => (int) env('IMAGE_JPEG_QUALITY', 82),
    'image_webp_quality' => (int) env('IMAGE_WEBP_QUALITY', 80),
];
