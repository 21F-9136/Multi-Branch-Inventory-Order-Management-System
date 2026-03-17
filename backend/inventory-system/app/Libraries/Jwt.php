<?php

namespace App\Libraries;

use RuntimeException;

final class Jwt
{
    private const ALG = 'HS256';
    private const TYP = 'JWT';

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, string $secret): string
    {
        $header = ['typ' => self::TYP, 'alg' => self::ALG];

        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, string $secret, int $leewaySeconds = 30): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $headerRaw = self::base64UrlDecode($encodedHeader);
        $payloadRaw = self::base64UrlDecode($encodedPayload);
        $signature = self::base64UrlDecode($encodedSignature);

        $header = json_decode($headerRaw, true);
        $payload = json_decode($payloadRaw, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('Invalid token encoding.');
        }

        if (($header['typ'] ?? null) !== self::TYP || ($header['alg'] ?? null) !== self::ALG) {
            throw new RuntimeException('Unsupported token type.');
        }

        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $now = time();
        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && ($now + $leewaySeconds) < (int) $payload['nbf']) {
            throw new RuntimeException('Token not yet valid.');
        }

        if (isset($payload['exp']) && is_numeric($payload['exp']) && ($now - $leewaySeconds) >= (int) $payload['exp']) {
            throw new RuntimeException('Token expired.');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 encoding.');
        }

        return $decoded;
    }
}
