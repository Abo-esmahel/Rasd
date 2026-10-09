<?php

namespace App\Support;

/**
 * اكتشاف الثنائيات المحلية (Chrome/ffmpeg) بشكل محمول:
 * لينكس + ويندوز + macOS، بلا أي مسار مثبت لجهاز معين.
 * ملاحظة: نستخدم is_file لا is_executable لأن الأخير غير موثوق على ويندوز،
 * وكل مرشح هنا مسار تنفيذي معروف أو نتيجة PATH (قابلة للتنفيذ بالتعريف).
 */
final class LocalBinary
{
    public static function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    public static function findChrome(): ?string
    {
        $env = trim((string) env('CHROME_BIN', ''));
        if ($env !== '' && is_file($env)) {
            return $env;
        }

        $candidates = [];
        $home = trim((string) ($_SERVER['HOME'] ?? getenv('HOME') ?: ''));
        if ($home !== '') {
            $candidates[] = $home.'/.local/bin/google-chrome-stable';
            $candidates[] = $home.'/.local/bin/google-chrome';
        }
        array_push(
            $candidates,
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        );
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach (['google-chrome-stable', 'google-chrome', 'chromium', 'chromium-browser', 'chrome'] as $bin) {
            $found = self::findOnPath($bin);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public static function findFfmpeg(): ?string
    {
        $env = trim((string) env('FFMPEG_BIN', ''));
        if ($env !== '' && is_file($env)) {
            return $env;
        }
        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'C:\Program Files\ffmpeg\bin\ffmpeg.exe'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return self::findOnPath('ffmpeg');
    }

    /** بحث PATH يعمل على لينكس (command -v) وويندوز (where). */
    public static function findOnPath(string $bin): ?string
    {
        try {
            if (! function_exists('shell_exec')) {
                return null;
            }
            $probe = self::isWindows() ? 'where' : 'command -v';
            $null = self::isWindows() ? 'NUL' : '/dev/null';
            $raw = shell_exec($probe.' '.escapeshellarg($bin).' 2>'.$null);
            foreach (preg_split('/\R/', (string) $raw) ?: [] as $line) {
                $path = trim($line);
                if ($path !== '' && is_file($path)) {
                    return $path;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /** file:// صالح للكروم على كل الأنظمة (شرط ويندوز المائلة). */
    public static function fileUrl(string $path): string
    {
        $p = str_replace('\\', '/', $path);
        if (preg_match('#^[A-Za-z]:/#', $p)) {
            return 'file:///'.$p;
        }

        return 'file://'.$p;
    }
}
