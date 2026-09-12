<?php

namespace App\Services\Ai;

class AiResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $summary,
        public readonly string $body,
        public readonly array $warnings = [],
        public readonly array $uncertainties = [],
        public readonly string $raw = '',
        public readonly string $recommendations = '',
    ) {
    }

    public function toContent(): string
    {
        $parts = [];
        if (trim($this->title) !== '') {
            $parts[] = trim($this->title);
        }
        if (trim($this->summary) !== '') {
            $parts[] = trim($this->summary);
        }
        if (trim($this->body) !== '') {
            $parts[] = trim($this->body);
        }
        if (trim($this->recommendations) !== '') {
            $parts[] = 'التوصيات:' . "\n" . trim($this->recommendations);
        }

        return trim(implode("\n\n", array_filter($parts)));
    }
}
