<?php
return [
    // Original File Preservation — لا ضغط، لا إعادة ترميز، لا canvas: الملف يصل كما هو 100%
    // القيم بالكيلوبايت (KB) — متوافقة مع validation max:xxx في Laravel
    // الحدود: 20MB صور، 100MB فيديو، 100MB صوت — ضمن حد PHP post_max_size=128M و upload_max_filesize=128M
    'max_image_size' => (int) env('MAX_IMAGE_SIZE', 20480), // KB
    'max_video_size' => (int) env('MAX_VIDEO_SIZE', 102400), // KB (100MB)
    'max_per_note' => (int) env('MAX_ATTACHMENTS_PER_NOTE', 10),
    'max_per_submission' => (int) env('MAX_ATTACHMENTS_PER_SUBMISSION', 5),
    // حد الصوت بالبايت — يُستخدم فعلياً في NoteService::validateFile
    'max_audio_size' => (int) env('MAX_AUDIO_SIZE', 100 * 1024 * 1024),
];
