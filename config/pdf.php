<?php

return [

    'queue' => filter_var(env('PDF_QUEUE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'queue_grace_seconds' => (int) env('PDF_QUEUE_GRACE_SECONDS', 10),

    'public_base_url' => rtrim((string) env('PDF_PUBLIC_BASE_URL', ''), '/'),

    'signed_url_ttl_minutes' => (int) env('PDF_SIGNED_URL_TTL_MINUTES', 60 * 24 * 7),

    'inline_original_max_kb' => (int) env('PDF_INLINE_ORIGINAL_MAX_KB', 3072),
    'inline_downscale_px' => (int) env('PDF_INLINE_DOWNSCALE_PX', 1400),
    'inline_total_max_kb' => (int) env('PDF_INLINE_TOTAL_MAX_KB', 8192),
];
