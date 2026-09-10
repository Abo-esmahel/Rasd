<?php

namespace App\Support;

class SyrianPhone
{
    
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) return null;
        $clean = trim($raw);
        if ($clean === '') return null;

        
        $clean = preg_replace('/[\s\-\(\)\.]+/', '', $clean);
        if ($clean === '' || $clean === null) return null;

        
        
        $hasPlus = str_starts_with($clean, '+');
        
        $digits = preg_replace('/\D+/', '', $clean);
        if ($digits === '' || $digits === null) return null;

        
        if ($hasPlus) {
            $clean = '+' . $digits;
        } else {
            $clean = $digits;
        }

        
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
            return '+963' . substr($clean, 1); 
        }
        if (str_starts_with($clean, '9')) {
            return '+963' . $clean;
        }

        
        return $clean;
    }

    
    public static function isValidNormalized(?string $phone): bool
    {
        if ($phone === null || $phone === '') return false;
        return (bool) preg_match('/^\+9639\d{8}$/', $phone);
    }

    
    public static function toWhatsapp(?string $normalized): ?string
    {
        if ($normalized === null || $normalized === '') return null;
        $n = self::normalize($normalized);
        if (!self::isValidNormalized($n)) return null;
        return ltrim($n, '+');
    }

    
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
