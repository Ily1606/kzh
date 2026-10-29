<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;

class UsernameHelper
{
    /**
     * The shape a username may take: letters and digits, with single dashes
     * between them.
     */
    public const PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/';

    /**
     * RFC 4122 UUID pattern (case-insensitive).
     * Used to prevent usernames that look like UUIDs to avoid route conflicts.
     */
    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Check if the given username looks like a UUID.
     */
    public static function isUuidFormat(string $username): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $username);
    }

    /**
     * Generate a random username that stays easy to read out loud.
     *
     * The letters l and o and the digits 0 and 1 are left out because they
     * are easily mistaken for each other.
     */
    public static function generate(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';

        $suffix = '';
        for ($position = 0; $position < 8; $position++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'user-'.$suffix;
    }

    /**
     * Check if the given unique constraint violation exception is related
     * to the username column or index.
     */
    public static function isViolation(UniqueConstraintViolationException $e): bool
    {
        return in_array('username', $e->columns, true)
            || str_contains((string) $e->index, 'username');
    }
}
