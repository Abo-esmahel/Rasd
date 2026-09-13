<?php

namespace App\Services\Localization;

interface TranslationProviderInterface
{
    public function name(): string;

    public function translateBatch(array $texts, string $sourceLang, string $targetLang): array;
}
