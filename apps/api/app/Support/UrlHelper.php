<?php

namespace App\Support;

use Spatie\Url\Url;

class UrlHelper
{
    /**
     * Generate the frontend password reset URL.
     */
    public static function generateFrontendResetUrl(string $email, string $token): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return (string) Url::fromString($frontendUrl)
            ->withPath('/reset-password')
            ->withQueryParameter('token', $token)
            ->withQueryParameter('email', $email);
    }
}
