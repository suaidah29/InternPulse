<?php

namespace App\Services;

/**
 * Time-based one-time passwords (RFC 6238) with a SHA-1 / 6 digit / 30 second
 * profile, which Google Authenticator, 1Password, Authy and Microsoft
 * Authenticator all accept as the default TOTP entry.
 */
class TwoFactor
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 20 random bytes -> 32 base32 characters (160 bits), the size every authenticator app supports. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function base32Encode(string $raw): string
    {
        $bits = '';
        foreach (str_split($raw) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded) ?? '');
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $raw = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $raw .= chr(bindec($chunk));
            }
        }

        return $raw;
    }

    public static function codeAt(string $secret, int $timestamp): string
    {
        $hash = hash_hmac('sha1', pack('J', intdiv($timestamp, self::PERIOD)), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Accepts the current code and one period either side, for clock drift between phone and server. */
    public static function verify(?string $secret, string $code, int $window = 1, ?int $now = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($secret === null || $secret === '' || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now ??= time();
        for ($drift = -$window; $drift <= $window; $drift++) {
            if (hash_equals(self::codeAt($secret, $now + ($drift * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function otpauthUri(string $account, string $secret, string $issuer = 'InternPulse'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /** Eight single-use codes; 10 base32 characters each is about 50 bits of entropy. */
    public static function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            foreach (str_split(self::base32Encode(random_bytes(7))) as $char) {
                if (strlen($raw) === 10) {
                    break;
                }
                $raw .= $char;
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5, 5);
        }

        return $codes;
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    /** Codes are stored hashed: a leaked database row must not be a working sign-in. */
    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', self::normalizeRecoveryCode($code));
    }

    public static function hashRecoveryCodes(array $codes): array
    {
        return array_values(array_map([self::class, 'hashRecoveryCode'], $codes));
    }

    /** Returns the index of the matching unused code, or null. */
    public static function matchRecoveryCode(array $hashedCodes, string $code): ?int
    {
        $candidate = self::hashRecoveryCode($code);
        foreach ($hashedCodes as $index => $hash) {
            if (is_string($hash) && hash_equals($hash, $candidate)) {
                return (int) $index;
            }
        }

        return null;
    }
}
