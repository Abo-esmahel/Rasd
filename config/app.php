<?php

return [

    
    'name' => env('APP_NAME', 'Laravel'),

    
    'env' => env('APP_ENV', 'production'),

    
    'debug' => (bool) env('APP_DEBUG', false),

    
    'url' => env('APP_URL', 'http://localhost'),


    'share_url' => env('SHARE_URL'),

    // المنطقة الزمنية المعتمدة لحساب "يوم التقرير" (observed_at → report_date).
    // كانت ثابتة UTC فتكسر توافق منتصف الليل عند تغيير البيئة — الآن عبر APP_TIMEZONE.
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    
    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    
    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
    'vapid_subject' => env('VAPID_SUBJECT', 'mailto:admin@rasd.local'),

];
