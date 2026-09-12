<?php

namespace App\Exceptions\Ai;

class AiRateLimitException extends AiException
{
    public function __construct(
        ?string $message = null,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?? __('api.ai_rate_default'), 0, $previous);
    }
}
