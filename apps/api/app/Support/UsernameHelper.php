<?php

namespace App\Support;

class UsernameHelper
{
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
}
