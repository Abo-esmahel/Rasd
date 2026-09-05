<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class JwtService
{
    private string $secret;
    private int $expiry;

    public function __construct()
    {
        $this->secret = config('jwt.secret', '');
        $this->expiry = (int) config('jwt.expiry_minutes', 60);
    }

    public function generateToken(User $user): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes($this->expiry)->timestamp,
        ]));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$signature";
    }

    public function validateToken(string $token): ?User
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadData = json_decode($this->base64UrlDecode($payload), true);

        if (!$payloadData || !isset($payloadData['exp'])) {
            return null;
        }

        if ($payloadData['exp'] < now()->timestamp) {
            return null;
        }

        $userId = $payloadData['sub'] ?? null;
        if (!$userId) {
            return null;
        }

        $blacklisted = Cache::get("jwt_blacklist:$token");
        if ($blacklisted) {
            return null;
        }

        return User::find($userId);
    }

    public function blacklistToken(string $token): void
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return;
        }

        $payloadData = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!$payloadData || !isset($payloadData['exp'])) {
            return;
        }

        $ttl = max($payloadData['exp'] - now()->timestamp, 0);
        if ($ttl > 0) {
            Cache::put("jwt_blacklist:$token", true, $ttl);
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
