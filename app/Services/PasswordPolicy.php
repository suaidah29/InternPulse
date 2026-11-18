<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

/**
 * One password policy for every place a password is set, so an account
 * created through an invitation is exactly as strong as one reset later.
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /** bcrypt silently ignores bytes past 72, so refuse longer input instead of truncating it. */
    public const MAX_LENGTH = 72;

    /**
     * @param  string|null  $name  the person's own name, rejected inside the password
     * @param  string|null  $email  also rejected (the part before the @)
     * @return array<int, mixed>
     */
    public static function rulesFor(?string $name = null, ?string $email = null): array
    {
        return [
            'required',
            'confirmed',
            Password::min(self::MIN_LENGTH)->max(self::MAX_LENGTH)->mixedCase()->numbers()->symbols(),
            self::notPersonal($name, $email),
        ];
    }

    /** @return array<int, mixed> */
    public static function rulesForUser(?object $user = null): array
    {
        return self::rulesFor($user->name ?? null, $user->email ?? null);
    }

    /** Rejects passwords built from the person's own name or email address. */
    public static function notPersonal(?string $name, ?string $email): \Closure
    {
        $parts = [];
        foreach ([$name, $email ? strstr($email, '@', true) : null] as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            foreach (preg_split('/[^a-z0-9]+/i', $value) ?: [] as $part) {
                if (strlen($part) >= 3) {
                    $parts[] = strtolower($part);
                }
            }
        }
        $parts = array_values(array_unique($parts));

        return function (string $attribute, mixed $value, \Closure $fail) use ($parts): void {
            $haystack = strtolower((string) $value);
            foreach ($parts as $part) {
                if (str_contains($haystack, $part)) {
                    $fail('The password must not contain your name or email address. / Kata laluan tidak boleh mengandungi nama atau e-mel anda.');

                    return;
                }
            }
        };
    }

    /** @return array<int, array{0: string, 1: string}> English and Malay bullets for the forms. */
    public static function summary(): array
    {
        return [
            ['At least '.self::MIN_LENGTH.' characters', 'Sekurang-kurangnya '.self::MIN_LENGTH.' aksara'],
            ['Upper and lower case letters', 'Huruf besar dan huruf kecil'],
            ['At least one number', 'Sekurang-kurangnya satu nombor'],
            ['At least one symbol, such as ! ? # $', 'Sekurang-kurangnya satu simbol, seperti ! ? # $'],
            ['Not your name or email address', 'Bukan nama atau alamat e-mel anda'],
        ];
    }
}
