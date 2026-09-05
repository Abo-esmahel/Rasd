<?php

namespace App\Support;

class SyrianPhone
{
    /**
     * Normalize any Syrian mobile format to international E.164: +9639XXXXXXXX
     *
     * Accepted inputs:
     *  0944123456      -> +963944123456
     *  944123456       -> +963944123456
     *  963944123456    -> +963944123456
     *  +963944123456   -> +963944123456
     *  00963944123456  -> +963944123456
     *
     *  Also tolerates spaces, dashes, parentheses.
     *
     * @return string|null  normalized phone or null if empty
     *  (returns attempted value even if invalid — use isValidNormalized to check)
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) return null;
        $clean = trim($raw);
        if ($clean === '') return null;

        // remove common separators but keep leading +
        $clean = preg_replace('/[\s\-\(\)\.]+/', '', $clean);
        if ($clean === '' || $clean === null) return null;

        // keep only digits and leading + — remove any other chars for safety
        // but we preserve + only at start
        $hasPlus = str_starts_with($clean, '+');
        // remove all non-digits
        $digits = preg_replace('/\D+/', '', $clean);
        if ($digits === '' || $digits === null) return null;

        // reconstruct with plus flag if original had plus
        if ($hasPlus) {
            $clean = '+' . $digits;
        } else {
            $clean = $digits;
        }

        // Apply transformation rules (order matters)
        if (str_starts_with($clean, '00963')) {
            return '+963' . substr($clean, 5);
        }
        if (str_starts_with($clean, '+963')) {
            return $clean;
        }
        if (str_starts_with($clean, '963')) {
            return '+' . $clean;
        }
        if (str_starts_with($clean, '09')) {
            return '+963' . substr($clean, 1); // 0 + 9XXXXXXXX -> +9639XXXXXXXX
        }
        if (str_starts_with($clean, '9')) {
            return '+963' . $clean;
        }

        // unknown prefix — return as-is (will be flagged invalid)
        return $clean;
    }

    /**
     * Check if normalized phone is valid Syrian mobile.
     * Expected: +9639 + 8 digits (total 13 chars)
     */
    public static function isValidNormalized(?string $phone): bool
    {
        if ($phone === null || $phone === '') return false;
        return (bool) preg_match('/^\+9639\d{8}$/', $phone);
    }

    /**
     * Convert normalized +963... to WhatsApp format (without +)
     * +963944123456 -> 963944123456
     */
    public static function toWhatsapp(?string $normalized): ?string
    {
        if ($normalized === null || $normalized === '') return null;
        $n = self::normalize($normalized);
        if (!self::isValidNormalized($n)) return null;
        return ltrim($n, '+');
    }

    /**
     * Build wa.me URL (without encoding text)
     */
    public static function whatsappUrl(?string $normalized, ?string $text = null): ?string
    {
        $wa = self::toWhatsapp($normalized);
        if ($wa === null) return null;
        $url = 'https://wa.me/' . $wa;
        if ($text !== null && $text !== '') {
            $url .= '?text=' . rawurlencode($text);
        }
        return $url;
    }
}
