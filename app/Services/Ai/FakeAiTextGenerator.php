<?php

namespace App\Services\Ai;


class FakeAiTextGenerator implements AiTextGeneratorInterface
{
    public static array $lastRequest = [];

    public function __construct(private readonly string $body = 'مسودة تجريبية مولدة آلياً للاختبار')
    {
    }

    public function generate(string $systemInstruction, string $userPrompt): AiResult
    {
        self::$lastRequest = ['system' => $systemInstruction, 'user' => $userPrompt, 'images' => []];

        return new AiResult(
            title: 'تقرير تجريبي',
            summary: 'ملخص تجريبي',
            body: $this->body,
            warnings: [],
            uncertainties: [],
            raw: $this->body,
            recommendations: 'توصية تجريبية',
        );
    }

    public function generateWithImages(string $systemInstruction, string $userPrompt, array $images): AiResult
    {
        self::$lastRequest = ['system' => $systemInstruction, 'user' => $userPrompt, 'images' => $images];

        return new AiResult(
            title: 'تقرير تجريبي بالصور',
            summary: 'ملخص تجريبي',
            body: $this->body,
            warnings: [],
            uncertainties: [],
            raw: $this->body,
            recommendations: 'توصية تجريبية',
        );
    }
}
