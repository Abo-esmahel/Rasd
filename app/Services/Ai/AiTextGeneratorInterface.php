<?php

namespace App\Services\Ai;

interface AiTextGeneratorInterface
{
    public function generate(string $systemInstruction, string $userPrompt): AiResult;


    public function generateWithImages(string $systemInstruction, string $userPrompt, array $images): AiResult;
}
