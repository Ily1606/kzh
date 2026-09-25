<?php

namespace App\Enums;

enum AuthEventType: string
{
    case Registered = 'registered';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';

    /**
     * Human readable label used for logging.
     */
    public function label(): string
    {
        return match ($this) {
            self::Registered => 'User registered',
            self::LoggedIn => 'User logged in',
            self::LoggedOut => 'User logged out',
        };
    }
}
