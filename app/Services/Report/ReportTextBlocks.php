<?php

namespace App\Services\Report;

/**
 * مقاطع منسّقة للنصوص الطويلة — لعرض الجوال.
 *
 * المشكلة: الـAPI كان يرسل summary/content/recommendations كنص خام، فعرضه
 * الجوال كفقرة واحدة: الأسطر التي كتبها الكاتب بنفسه تختفي، و"1." و"-"
 * تصير حرفاً داخل السطر، والأرقام تتلاصق.
 *
 * الحل: reuse محركات ReportEngine القائمة (splitParagraphs /
 * splitRecommendations) بلا سطر جديد — نفس التي تبني الويب والـPDF، فمصدر
 * واحد للحقيقة ولا تكرار للمنطق. هذا لا يغيّر أي نص ولا يخترع شيئاً.
 *
 * المخرج blocks[]: كل عنصر {type, text} و.order اختياري:
 *   - paragraph : فقرة عادية
 *   - heading   : سطر قصير ينتهي ':' (سطر عنوان)
 *   - item      : عنصر قائمة مرقّمة (order = رقمه) أو منقّطة (order = 0)
 */
final class ReportTextBlocks
{
    /** @return array<int, array{type: string, text: string, order?: int}> */
    public static function build(?string $raw, string $mode = 'auto'): array
    {
        $text = ReportEngine::normalizeText($raw);
        if ($text === '') {
            return [];
        }

        if ($mode === 'recommendations') {
            return self::fromRecommendations($text);
        }

        return self::fromLines(ReportEngine::splitParagraphs($text));
    }

    /**
     * @return array<int, array{type: string, text: string, order?: int}>
     */
    private static function fromRecommendations(string $text): array
    {
        $split = ReportEngine::splitRecommendations($text);
        $out = [];

        foreach ($split['items'] as $i => $item) {
            $out[] = ['type' => 'item', 'text' => $item, 'order' => $i + 1];
        }
        foreach ($split['paragraphs'] as $paragraph) {
            $block = self::paragraph($paragraph);
            if ($block !== null) {
                $out[] = $block;
            }
        }

        return $out;
    }

    /**
     * @param  string[]  $lines
     * @return array<int, array{type: string, text: string, order?: int}>
     */
    private static function fromLines(array $lines): array
    {
        $out = [];
        $order = 0;

        foreach ($lines as $line) {
            $item = self::asItem($line);
            if ($item !== null) {
                $out[] = [
                    'type' => 'item',
                    'text' => $item['text'],
                    'order' => $item['order'] > 0 ? $item['order'] : ++$order,
                ];
                continue;
            }
            $block = self::paragraph($line);
            if ($block !== null) {
                $out[] = $block;
            }
        }

        return $out;
    }

    /**
     * @return array{type: string, text: string}|null
     */
    private static function paragraph(string $line): ?array
    {
        $t = trim($line);
        if ($t === '') {
            return null;
        }

        if (mb_strlen($t) <= 60 && preg_match('/[:：]$/u', $t) === 1) {
            return ['type' => 'heading', 'text' => rtrim($t, ':：')];
        }

        return ['type' => 'paragraph', 'text' => $t];
    }

    /**
     * يكتشف عنصر القائمة المرقّم/المنقّط في بداية السطر.
     *
     * @return array{text: string, order: int}|null
     */
    private static function asItem(string $line): ?array
    {
        $t = trim($line);

        if (preg_match('/^(\d{1,2})\s*[.)]\s+(.*)$/u', $t, $m) === 1) {
            $body = trim($m[2]);
            if ($body !== '') {
                return ['text' => $body, 'order' => (int) $m[1]];
            }
        }

        if (preg_match('/^[\-•*–—]+\s+(.*)$/u', $t, $m) === 1) {
            $body = trim($m[1]);
            if ($body !== '') {
                return ['text' => $body, 'order' => 0];
            }
        }

        return null;
    }
}