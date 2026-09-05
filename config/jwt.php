<?php

return [
    'secret' => env('JWT_SECRET', ''),
    'expiry_minutes' => (int) env('JWT_EXPIRY_MINUTES', 60),
];
