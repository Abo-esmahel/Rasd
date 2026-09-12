<?php

return [

    'enabled' => (bool) env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'gemini'),


    'max_images_per_generation' => (int) env('AI_MAX_IMAGES_PER_GENERATION', 3),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        // Verified live 2026-09-11: gemini-2.5-flash is retired for new users.
        // Use a currently served flash model (see /v1beta/models).
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        // 3.x flash models spend thinking tokens from the same budget: keep generous.
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 4096),
        // ملاحظة: توليد/تعبئة الصور بالذكاء الاصطناعي مُزال — الرسم محلي (GD) حصراً.
        // Gemini مسؤول عن توليد النصوص فقط (generate-data / المسودات).
    ],


    'context_path' => storage_path('app/ai/report-context.txt'),
    'context_version' => '1.0',
];
