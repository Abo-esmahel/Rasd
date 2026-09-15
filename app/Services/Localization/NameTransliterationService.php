<?php

namespace App\Services\Localization;

class NameTransliterationService
{
    private const ARABIC_TO_LATIN = [
        'أ' => 'a', 'إ' => 'i', 'آ' => 'aa', 'ا' => 'a',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
        'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
        'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z',
        'ع' => '', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
        'ه' => 'h', 'و' => 'w', 'ي' => 'y',
        'ة' => 'a', 'ى' => 'a', 'ء' => '',
    ];

    private const LATIN_TO_ARABIC_MAP = [
        'kh' => 'خ', 'gh' => 'غ', 'sh' => 'ش',
        'th' => 'ث', 'dh' => 'ذ', 'ch' => 'ش',
        'a' => 'ا', 'b' => 'ب', 't' => 'ت', 'j' => 'ج',
        'h' => 'ه', 'd' => 'د', 'r' => 'ر', 'z' => 'ز',
        's' => 'س', 'f' => 'ف', 'q' => 'ق', 'k' => 'ك',
        'l' => 'ل', 'm' => 'م', 'n' => 'ن', 'w' => 'و',
        'y' => 'ي', 'p' => 'ب', 'v' => 'ف', 'g' => 'غ',
        'c' => 'ك', 'x' => 'ك',
    ];

    private const VOWELS = ['a', 'e', 'i', 'o', 'u'];

    public function isArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    public function arabicToLatin(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($parts as $part) {
            $result[] = $this->transliterateArabicWord($part);
        }
        return $this->formatLatin(implode(' ', $result));
    }

    public function latinToArabic(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($parts as $part) {
            $result[] = $this->transliterateLatinWord($part);
        }
        return implode(' ', $result);
    }

    private function transliterateArabicWord(string $word): string
    {
        $chars = mb_str_split($word);
        $latin = '';
        $len = count($chars);
        $i = 0;

        while ($i < $len) {
            $char = $chars[$i];

            $map = self::ARABIC_TO_LATIN[$char] ?? null;
            if ($map !== null) {
                $latin .= $map;
            } elseif (preg_match('/[a-zA-Z0-9]/', $char)) {
                $latin .= $char;
            }
            $i++;
        }

        return $latin;
    }

    private function transliterateLatinWord(string $word): string
    {
        $lower = mb_strtolower($word);
        $arabic = '';
        $len = mb_strlen($lower);
        $i = 0;

        while ($i < $len) {
            $matched = false;

            foreach (['kh', 'gh', 'sh', 'th', 'dh', 'ch'] as $combo) {
                $comboLen = mb_strlen($combo);
                if (mb_substr($lower, $i, $comboLen) === $combo) {
                    $arabic .= self::LATIN_TO_ARABIC_MAP[$combo];
                    $i += $comboLen;
                    $matched = true;
                    break;
                }
            }
            if ($matched) continue;

            $char = mb_substr($lower, $i, 1);

            if ($char === ' ') {
                $arabic .= ' ';
                $i++;
                continue;
            }

            if (isset(self::LATIN_TO_ARABIC_MAP[$char])) {
                $arabic .= self::LATIN_TO_ARABIC_MAP[$char];
            }

            $i++;
        }

        return $arabic;
    }

    private function formatLatin(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        $text = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $text);
        $text = ucwords($text);
        return $text;
    }

    public function generateLatinName(string $arabicName): ?string
    {
        $latin = $this->arabicToLatin($arabicName);
        return $latin !== '' ? $latin : null;
    }

    public function generateArabicName(string $latinName): ?string
    {
        $arabic = $this->latinToArabic($latinName);
        return $arabic !== '' ? $arabic : null;
    }
}
